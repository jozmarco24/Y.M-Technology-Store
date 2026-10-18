<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoCaja;
use App\Enums\EstadoSerie;
use App\Enums\EstadoVenta;
use App\Enums\MetodoPago;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDocumentoCliente;
use App\Enums\TipoMovimiento;
use App\Enums\TipoMovimientoCaja;
use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class VentaService
{
    public function __construct(
        protected NumeracionService $numeracionService,
        protected CajaService $cajaService,
        protected InventarioService $inventarioService
    ) {}

    /**
     * Registrar una venta completa dentro de una transacción.
     *
     * @param  array{
     *     cliente_id: int,
     *     tipo_comprobante: string|TipoComprobanteVenta,
     *     serie_comprobante?: string,
     *     observaciones?: string|null,
     *     descuento_global?: float|int|string
     * }  $datos
     * @param  array<int, array{
     *     producto_id: int,
     *     serie_id?: int|null,
     *     cantidad?: int,
     *     precio_unitario?: float|int|string,
     *     descuento?: float|int|string,
     *     descripcion?: string
     * }>  $items
     * @param  array<int, array{
     *     metodo: string|MetodoPago,
     *     monto: float|int|string,
     *     referencia?: string|null
     * }>  $pagos
     */
    public function registrar(array $datos, array $items, array $pagos, User $vendedor): Venta
    {
        if (empty($items)) {
            throw new InvalidArgumentException('La venta debe tener al menos un producto o ítem.');
        }

        if (empty($pagos)) {
            throw new InvalidArgumentException('Debe registrar al menos un método de pago.');
        }

        // 1. Exige caja abierta del vendedor
        $cajaSesion = CajaSesion::where('user_id', $vendedor->id)
            ->where('estado', EstadoCaja::ABIERTA->value)
            ->first();

        if ($cajaSesion === null) {
            throw new RuntimeException("El vendedor {$vendedor->name} no tiene una sesión de caja abierta. Debe abrir caja antes de vender.");
        }

        return DB::transaction(function () use ($datos, $items, $pagos, $vendedor, $cajaSesion) {
            // Bloqueamos la sesión de caja del vendedor para consistencia
            $sesion = CajaSesion::where('id', $cajaSesion->id)->lockForUpdate()->firstOrFail();
            if (! $sesion->estaAbierta()) {
                throw new RuntimeException('La sesión de caja se encuentra cerrada.');
            }

            // 2. Cliente y validaciones tributarias
            $cliente = Cliente::findOrFail($datos['cliente_id']);
            if (! $cliente->activo) {
                throw new InvalidArgumentException('El cliente seleccionado se encuentra inactivo.');
            }

            $tipoComprobante = $datos['tipo_comprobante'] instanceof TipoComprobanteVenta
                ? $datos['tipo_comprobante']
                : TipoComprobanteVenta::from($datos['tipo_comprobante']);

            if ($tipoComprobante === TipoComprobanteVenta::FACTURA) {
                if ($cliente->tipo_documento !== TipoDocumentoCliente::RUC || empty($cliente->numero_documento)) {
                    throw new InvalidArgumentException('La Factura exige que el cliente tenga RUC válido.');
                }
            }

            $esAdmin = $vendedor->hasRole('admin');

            // 3. Procesar ítems y calcular montos
            $subtotalVenta = 0.0;
            $descuentoItems = 0.0;
            $totalBrutoVenta = 0.0;
            $seriesEnVenta = [];
            $detallesParaCrear = [];

            foreach ($items as $index => $itemData) {
                $productoId = (int) $itemData['producto_id'];
                $producto = Producto::where('id', $productoId)->lockForUpdate()->firstOrFail();

                if (! $producto->activo) {
                    throw new InvalidArgumentException("El producto '{$producto->nombre}' se encuentra inactivo.");
                }

                $cantidad = isset($itemData['cantidad']) ? (int) $itemData['cantidad'] : 1;
                if ($cantidad <= 0) {
                    throw new InvalidArgumentException('La cantidad debe ser mayor a 0 en el ítem '.($index + 1).'.');
                }

                $precioBase = isset($itemData['precio_unitario'])
                    ? round((float) $itemData['precio_unitario'], 2)
                    : (float) $producto->precio_venta;

                $descuentoLinea = isset($itemData['descuento']) ? round((float) $itemData['descuento'], 2) : 0.0;
                if ($descuentoLinea < 0) {
                    throw new InvalidArgumentException('El descuento no puede ser negativo en el ítem '.($index + 1).'.');
                }

                $precioFinalItem = $precioBase - $descuentoLinea;
                if ($precioFinalItem < 0) {
                    throw new InvalidArgumentException('El precio con descuento no puede ser negativo en el ítem '.($index + 1).'.');
                }

                // Control de descuento máximo según rol: vendedor máx 5%, admin ilimitado
                if (! $esAdmin && $precioBase > 0) {
                    $porcentajeDescuento = round(($descuentoLinea / $precioBase) * 100, 2);
                    if ($porcentajeDescuento > 5.0) {
                        throw new InvalidArgumentException("El descuento del {$porcentajeDescuento}% supera el máximo permitido para vendedores (5%).");
                    }
                }

                $costoUnitario = 0.0;
                $serieId = null;

                if ($producto->controla_series) {
                    if (empty($itemData['serie_id'])) {
                        throw new InvalidArgumentException("El producto '{$producto->nombre}' controla series y requiere especificar una serie.");
                    }
                    if ($cantidad !== 1) {
                        throw new InvalidArgumentException('Cada ítem con serie debe tener cantidad 1.');
                    }

                    $serieId = (int) $itemData['serie_id'];
                    if (in_array($serieId, $seriesEnVenta, true)) {
                        throw new InvalidArgumentException("La serie ID {$serieId} está duplicada en la misma venta.");
                    }
                    $seriesEnVenta[] = $serieId;

                    // Bloquear la serie para evitar concurrencia
                    $serie = Serie::where('id', $serieId)->lockForUpdate()->first();
                    if (! $serie || $serie->producto_id !== $producto->id) {
                        throw new InvalidArgumentException("La serie especificada no pertenece al producto '{$producto->nombre}'.");
                    }

                    if ($serie->estado !== EstadoSerie::DISPONIBLE) {
                        throw new InvalidArgumentException("La serie '{$serie->numero_serie}' no está disponible para la venta (estado actual: {$serie->estado->value}).");
                    }

                    $costoUnitario = (float) $serie->costo;

                    // No permitir vender por debajo del costo si no es admin
                    if (! $esAdmin && $precioFinalItem < $costoUnitario) {
                        throw new InvalidArgumentException("No se permite vender el producto '{$producto->nombre}' (S/ {$precioFinalItem}) por debajo de su costo (S/ {$costoUnitario}).");
                    }

                    $detallesParaCrear[] = [
                        'producto' => $producto,
                        'serie' => $serie,
                        'cantidad' => 1,
                        'precio_unitario' => $precioBase,
                        'descuento' => $descuentoLinea,
                        'subtotal' => $precioFinalItem,
                        'costo_unitario' => $costoUnitario,
                        'descripcion' => $itemData['descripcion'] ?? "{$producto->nombre} (SN: {$serie->numero_serie})",
                    ];

                    $totalBrutoVenta += $precioFinalItem;
                } else {
                    // Producto sin serie: verificar stock disponible
                    $stockActual = $this->inventarioService->stockDisponible($producto);
                    if ($stockActual < $cantidad) {
                        throw new InvalidArgumentException("Stock insuficiente para '{$producto->nombre}'. Disponible: {$stockActual}, solicitado: {$cantidad}.");
                    }

                    $costoUnitario = (float) $producto->precio_compra_referencial;

                    if (! $esAdmin && $precioFinalItem < $costoUnitario) {
                        throw new InvalidArgumentException("No se permite vender el producto '{$producto->nombre}' por debajo de su costo referencial.");
                    }

                    $subtotalLinea = round($precioFinalItem * $cantidad, 2);

                    $detallesParaCrear[] = [
                        'producto' => $producto,
                        'serie' => null,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precioBase,
                        'descuento' => $descuentoLinea,
                        'subtotal' => $subtotalLinea,
                        'costo_unitario' => $costoUnitario,
                        'descripcion' => $itemData['descripcion'] ?? $producto->nombre,
                    ];

                    $totalBrutoVenta += $subtotalLinea;
                }
            }

            // Descuento global adicional si existe
            $descuentoGlobal = isset($datos['descuento_global']) ? round((float) $datos['descuento_global'], 2) : 0.0;
            if ($descuentoGlobal < 0) {
                throw new InvalidArgumentException('El descuento global no puede ser negativo.');
            }
            if (! $esAdmin && $totalBrutoVenta > 0 && $descuentoGlobal > 0) {
                $porcentajeGlobal = round(($descuentoGlobal / $totalBrutoVenta) * 100, 2);
                if ($porcentajeGlobal > 5.0) {
                    throw new InvalidArgumentException("El descuento global del {$porcentajeGlobal}% supera el 5% permitido para vendedores.");
                }
            }

            $totalNetoVenta = round(max(0.0, $totalBrutoVenta - $descuentoGlobal), 2);

            // Cálculo de Subtotal e IGV (18% en Perú)
            // En el sistema los precios de venta son finales (con IGV incluido)
            $subtotalCalculado = round($totalNetoVenta / 1.18, 2);
            $igvCalculado = round($totalNetoVenta - $subtotalCalculado, 2);

            // Validación de Boleta >= S/ 700 exige cliente identificado (DNI, RUC o CE, no SIN_DOC)
            if ($tipoComprobante === TipoComprobanteVenta::BOLETA && $totalNetoVenta >= 700.00) {
                if ($cliente->tipo_documento === TipoDocumentoCliente::SIN_DOC || empty($cliente->numero_documento)) {
                    throw new InvalidArgumentException('Las boletas de venta con importe igual o superior a S/ 700.00 exigen identificar al cliente con DNI u otro documento.');
                }
            }

            // 4. Validar pagos y calcular vuelto si aplica
            $totalPagado = 0.0;
            $totalEfectivo = 0.0;
            foreach ($pagos as $p) {
                $montoPago = round((float) $p['monto'], 2);
                if ($montoPago <= 0) {
                    throw new InvalidArgumentException('El monto de cada pago debe ser mayor a cero.');
                }
                $metodo = $p['metodo'] instanceof MetodoPago ? $p['metodo'] : MetodoPago::from($p['metodo']);
                $totalPagado += $montoPago;
                if ($metodo === MetodoPago::EFECTIVO) {
                    $totalEfectivo += $montoPago;
                }
            }
            $totalPagado = round($totalPagado, 2);

            if ($totalPagado < $totalNetoVenta) {
                $falta = round($totalNetoVenta - $totalPagado, 2);
                throw new InvalidArgumentException("La suma de pagos (S/ {$totalPagado}) es menor al total de la venta (S/ {$totalNetoVenta}). Faltan S/ {$falta}.");
            }

            $vuelto = round($totalPagado - $totalNetoVenta, 2);
            if ($vuelto > 0) {
                if ($totalEfectivo < $vuelto) {
                    throw new InvalidArgumentException("Solo se permite vuelto cuando el pago en efectivo es suficiente. Vuelto necesario: S/ {$vuelto}, efectivo entregado: S/ {$totalEfectivo}.");
                }
            }

            // 5. Asignar correlativo seguro con NumeracionService
            $serieSolicitada = $datos['serie_comprobante'] ?? null;
            $correlativoData = $this->numeracionService->obtenerSiguienteCorrelativo($tipoComprobante, $serieSolicitada);

            // Generar código único para la venta: VEN-000001
            $siguienteIdVenta = (int) (Venta::max('id') ?? 0) + 1;
            $codigoVenta = 'VEN-'.str_pad((string) $siguienteIdVenta, 6, '0', STR_PAD_LEFT);

            // 6. Crear el registro de Venta
            $venta = Venta::create([
                'codigo' => $codigoVenta,
                'cliente_id' => $cliente->id,
                'vendedor_id' => $vendedor->id,
                'caja_sesion_id' => $sesion->id,
                'tipo_comprobante' => $tipoComprobante,
                'serie_comprobante' => $correlativoData['serie'],
                'numero_comprobante' => $correlativoData['numero'],
                'subtotal' => $subtotalCalculado,
                'descuento' => $descuentoGlobal,
                'igv' => $igvCalculado,
                'total' => $totalNetoVenta,
                'estado' => EstadoVenta::COMPLETADA,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            // 7. Crear detalles y actualizar inventario / series
            foreach ($detallesParaCrear as $itemInfo) {
                $producto = $itemInfo['producto'];
                $serie = $itemInfo['serie'];

                $detalle = VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'serie_id' => $serie?->id,
                    'descripcion' => $itemInfo['descripcion'],
                    'cantidad' => $itemInfo['cantidad'],
                    'precio_unitario' => $itemInfo['precio_unitario'],
                    'descuento' => $itemInfo['descuento'],
                    'subtotal' => $itemInfo['subtotal'],
                    'costo_unitario' => $itemInfo['costo_unitario'],
                ]);

                if ($serie !== null) {
                    // Pasar serie a vendida
                    $serie->update([
                        'estado' => EstadoSerie::VENDIDA,
                        'observaciones' => trim(($serie->observaciones ?? '')."\nVendido en {$venta->codigo}"),
                    ]);

                    $stockActual = $this->inventarioService->stockDisponible($producto);

                    MovimientoInventario::create([
                        'producto_id' => $producto->id,
                        'serie_id' => $serie->id,
                        'tipo' => TipoMovimiento::SALIDA,
                        'cantidad' => -1,
                        'stock_resultante' => $stockActual,
                        'referencia_tipo' => Venta::class,
                        'referencia_id' => $venta->id,
                        'motivo' => "Venta {$venta->codigo} ({$venta->tipo_comprobante->etiqueta()} {$venta->serie_comprobante}-{$venta->numero_comprobante})",
                        'user_id' => $vendedor->id,
                    ]);
                } else {
                    $stockPrevio = $this->inventarioService->stockDisponible($producto);
                    $nuevoStock = $stockPrevio - $itemInfo['cantidad'];

                    MovimientoInventario::create([
                        'producto_id' => $producto->id,
                        'serie_id' => null,
                        'tipo' => TipoMovimiento::SALIDA,
                        'cantidad' => -$itemInfo['cantidad'],
                        'stock_resultante' => $nuevoStock,
                        'referencia_tipo' => Venta::class,
                        'referencia_id' => $venta->id,
                        'motivo' => "Venta {$venta->codigo} ({$venta->tipo_comprobante->etiqueta()} {$venta->serie_comprobante}-{$venta->numero_comprobante})",
                        'user_id' => $vendedor->id,
                    ]);
                }
            }

            // 8. Crear pagos y registrar ingreso en caja por cada pago (descontando vuelto en efectivo si hubo)
            $vueltoRestante = $vuelto;
            foreach ($pagos as $p) {
                $metodo = $p['metodo'] instanceof MetodoPago ? $p['metodo'] : MetodoPago::from($p['metodo']);
                $montoPago = round((float) $p['monto'], 2);

                if ($metodo === MetodoPago::EFECTIVO && $vueltoRestante > 0) {
                    $descuentoVuelto = min($montoPago, $vueltoRestante);
                    $montoNetoPago = round($montoPago - $descuentoVuelto, 2);
                    $vueltoRestante = round($vueltoRestante - $descuentoVuelto, 2);
                } else {
                    $montoNetoPago = $montoPago;
                }

                if ($montoNetoPago > 0) {
                    VentaPago::create([
                        'venta_id' => $venta->id,
                        'metodo' => $metodo,
                        'monto' => $montoNetoPago,
                        'referencia' => $p['referencia'] ?? null,
                    ]);

                    // Ingreso a caja
                    $this->cajaService->registrarMovimiento(
                        cajaSesion: $sesion,
                        tipo: TipoMovimientoCaja::INGRESO,
                        concepto: "Venta {$venta->codigo} ({$venta->tipo_comprobante->etiqueta()} {$venta->serie_comprobante}-{$venta->numero_comprobante})",
                        monto: $montoNetoPago,
                        metodoPago: $metodo,
                        ventaId: $venta->id,
                        usuario: $vendedor
                    );
                }
            }

            return $venta->fresh(['cliente', 'vendedor', 'detalles.serie', 'detalles.producto', 'pagos', 'cajaSesion']);
        });
    }

    /**
     * Anular una venta completada.
     * Solo admin; motivo obligatorio; devuelve series a disponible; registra egreso en caja.
     */
    public function anular(Venta $venta, string $motivo, User $usuario): Venta
    {
        $motivoLimpio = trim($motivo);
        if ($motivoLimpio === '') {
            throw new InvalidArgumentException('El motivo de anulación es obligatorio.');
        }

        if (! $usuario->hasRole('admin')) {
            throw new RuntimeException('Solo un usuario administrador puede anular ventas.');
        }

        return DB::transaction(function () use ($venta, $motivoLimpio, $usuario) {
            $ventaBloqueada = Venta::where('id', $venta->id)->lockForUpdate()->firstOrFail();

            if ($ventaBloqueada->esAnulada()) {
                throw new RuntimeException("La venta {$ventaBloqueada->codigo} ya se encuentra anulada.");
            }

            if (! $ventaBloqueada->esCompletada()) {
                throw new RuntimeException('Solo se pueden anular ventas en estado completada.');
            }

            $ventaBloqueada->load(['detalles.producto', 'detalles.serie', 'pagos', 'cajaSesion']);

            // 1. Devolver series a disponible y registrar movimientos de anulación de inventario
            foreach ($ventaBloqueada->detalles as $detalle) {
                $producto = Producto::where('id', $detalle->producto_id)->lockForUpdate()->firstOrFail();

                if ($detalle->serie_id !== null && $detalle->serie !== null) {
                    $serie = Serie::where('id', $detalle->serie_id)->lockForUpdate()->firstOrFail();

                    // Regla de transición: VENDIDA -> DISPONIBLE
                    $serie->update([
                        'estado' => EstadoSerie::DISPONIBLE,
                        'observaciones' => trim(($serie->observaciones ?? '')."\nAnulación venta {$ventaBloqueada->codigo}: {$motivoLimpio}"),
                    ]);

                    $stockActual = $this->inventarioService->stockDisponible($producto);

                    MovimientoInventario::create([
                        'producto_id' => $producto->id,
                        'serie_id' => $serie->id,
                        'tipo' => TipoMovimiento::ANULACION,
                        'cantidad' => 1,
                        'stock_resultante' => $stockActual,
                        'referencia_tipo' => Venta::class,
                        'referencia_id' => $ventaBloqueada->id,
                        'motivo' => "Anulación de venta {$ventaBloqueada->codigo}: {$motivoLimpio}",
                        'user_id' => $usuario->id,
                    ]);
                } else {
                    $stockPrevio = $this->inventarioService->stockDisponible($producto);
                    $nuevoStock = $stockPrevio + $detalle->cantidad;

                    MovimientoInventario::create([
                        'producto_id' => $producto->id,
                        'serie_id' => null,
                        'tipo' => TipoMovimiento::ANULACION,
                        'cantidad' => $detalle->cantidad,
                        'stock_resultante' => $nuevoStock,
                        'referencia_tipo' => Venta::class,
                        'referencia_id' => $ventaBloqueada->id,
                        'motivo' => "Anulación de venta {$ventaBloqueada->codigo}: {$motivoLimpio}",
                        'user_id' => $usuario->id,
                    ]);
                }
            }

            // 2. Registrar egreso en caja por cada pago de la venta
            // Si la sesión original sigue abierta, egreso en ella. Si no, en la sesión abierta del admin.
            $sesionParaEgreso = null;
            if ($ventaBloqueada->cajaSesion->estaAbierta()) {
                $sesionParaEgreso = $ventaBloqueada->cajaSesion;
            } else {
                $sesionParaEgreso = $this->cajaService->obtenerSesionActiva($usuario);
            }

            if ($sesionParaEgreso !== null) {
                foreach ($ventaBloqueada->pagos as $pago) {
                    $this->cajaService->registrarMovimiento(
                        cajaSesion: $sesionParaEgreso,
                        tipo: TipoMovimientoCaja::EGRESO,
                        concepto: "Devolución por anulación de venta {$ventaBloqueada->codigo}",
                        monto: $pago->monto,
                        metodoPago: $pago->metodo,
                        ventaId: $ventaBloqueada->id,
                        usuario: $usuario
                    );
                }
            }

            // 3. Actualizar estado de la venta
            $ventaBloqueada->update([
                'estado' => EstadoVenta::ANULADA,
                'anulada_por' => $usuario->id,
                'anulada_en' => Carbon::now(),
                'motivo_anulacion' => $motivoLimpio,
            ]);

            return $ventaBloqueada->fresh();
        });
    }
}
