<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoPedido;
use App\Enums\EstadoSerie;
use App\Enums\MetodoEntrega;
use App\Enums\TipoMovimiento;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\PedidoEvento;
use App\Models\PedidoItem;
use App\Models\PedidoItemSerie;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PedidoService
{
    public function __construct(
        protected CarritoService $carritoService,
        protected EnvioService $envioService,
        protected CuponService $cuponService
    ) {}

    /**
     * Crear un pedido desde un carrito con reserva atómica de series (FIFO por fecha_ingreso).
     *
     * @param array{
     *     cliente_web_id?: int|null,
     *     metodo_entrega: string|MetodoEntrega,
     *     zona_envio_id?: int|null,
     *     direccion_snapshot?: array<string, mixed>|null,
     *     codigo_cupon?: string|null,
     *     tipo_comprobante: string,
     *     documento_tipo: string,
     *     documento_numero: string,
     *     razon_social?: string|null,
     *     direccion_fiscal?: string|null,
     *     email_contacto: string,
     *     telefono_contacto?: string|null,
     *     notas_cliente?: string|null
     * } $datos
     */
    public function crearDesdeCarrito(Carrito $carrito, array $datos): Pedido
    {
        return DB::transaction(function () use ($carrito, $datos) {
            // 1. Validar carrito y verificar que no esté vacío
            $itemsCarrito = $carrito->items()->with('producto')->get();
            if ($itemsCarrito->isEmpty()) {
                throw new InvalidArgumentException('El carrito está vacío. Agregue productos antes de continuar.');
            }

            // 2. Validaciones tributarias peruanas
            $tipoComprobante = strtolower(trim((string) $datos['tipo_comprobante']));
            $docTipo = strtoupper(trim((string) $datos['documento_tipo']));
            $docNumero = trim((string) $datos['documento_numero']);

            if ($tipoComprobante === 'factura') {
                if ($docTipo !== 'RUC' || strlen($docNumero) !== 11 || ! str_starts_with($docNumero, '10') && ! str_starts_with($docNumero, '20')) {
                    throw new InvalidArgumentException('Para emitir Factura es obligatorio ingresar un RUC válido de 11 dígitos.');
                }
                if (empty(trim((string) ($datos['razon_social'] ?? '')))) {
                    throw new InvalidArgumentException('La razón social es obligatoria para Factura.');
                }
            }

            // 3. Revalidar precios y reservar series físicas con lockForUpdate (FIFO)
            $subtotalPedido = 0.0;
            $itemsParaGuardar = [];

            foreach ($itemsCarrito as $idx => $item) {
                $pos = $idx + 1;
                /** @var Producto $producto */
                $producto = Producto::where('id', $item->producto_id)->lockForUpdate()->firstOrFail();

                if (! $producto->activo || ! $producto->publicado_web) {
                    throw new RuntimeException("El producto '{$producto->nombre}' ya no se encuentra disponible.");
                }

                $cantidad = $item->cantidad;
                $precioVigente = (float) $producto->precio_venta;

                // Buscar series disponibles ordenadas por fecha_ingreso ASC (FIFO)
                $seriesDisponibles = Serie::where('producto_id', $producto->id)
                    ->where('estado', EstadoSerie::DISPONIBLE)
                    ->orderBy('fecha_ingreso', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->limit($cantidad)
                    ->get();

                if ($seriesDisponibles->count() < $cantidad) {
                    throw new RuntimeException("Stock insuficiente para '{$producto->nombre}'. Se solicitaron {$cantidad} unidades y solo quedan {$seriesDisponibles->count()} disponibles.");
                }

                $lineaSubtotal = round($cantidad * $precioVigente, 2);
                $subtotalPedido += $lineaSubtotal;

                $itemsParaGuardar[] = [
                    'producto' => $producto,
                    'descripcion' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioVigente,
                    'subtotal' => $lineaSubtotal,
                    'series' => $seriesDisponibles,
                ];
            }

            $subtotalPedido = round($subtotalPedido, 2);

            // 4. Validar boleta >= S/ 700 exige DNI
            if ($tipoComprobante === 'boleta' && $subtotalPedido >= 700.00) {
                if ($docTipo !== 'DNI' || strlen($docNumero) !== 8) {
                    throw new InvalidArgumentException('Para boletas con importe igual o superior a S/ 700.00 es obligatorio consignar DNI del comprador.');
                }
            }

            // 5. Cotizar envío
            $cotizacionEnvio = $this->envioService->cotizar(
                $datos['metodo_entrega'],
                isset($datos['zona_envio_id']) ? (int) $datos['zona_envio_id'] : null
            );
            $costoEnvio = $cotizacionEnvio['costo'];

            // 6. Aplicar cupón si fue provisto
            $descuentoCupon = 0.0;
            $cuponId = null;
            if (! empty($datos['codigo_cupon'])) {
                $resultadoCupon = $this->cuponService->validar($datos['codigo_cupon'], $subtotalPedido);
                if ($resultadoCupon['valido'] && $resultadoCupon['cupon']) {
                    $descuentoCupon = $resultadoCupon['descuento'];
                    $cuponId = $resultadoCupon['cupon']->id;
                }
            }

            $totalPedido = max(0.0, round($subtotalPedido + $costoEnvio - $descuentoCupon, 2));

            // 7. Generar código correlativo PED-000001
            $ultimoId = (int) Pedido::withTrashed()->max('id');
            $nuevoCodigo = 'PED-'.str_pad((string) ($ultimoId + 1), 6, '0', STR_PAD_LEFT);

            // Tiempo de expiración de reserva (default 30 min)
            $minutosReserva = (int) config('services.pedidos.reserva_minutos', env('PEDIDO_RESERVA_MINUTOS', 30));
            $reservaExpiraEn = Carbon::now()->addMinutes($minutosReserva);

            // Asociar o encontrar cliente físico si aplica
            $clienteId = null;
            if ($docNumero !== '') {
                $clienteFisico = Cliente::where('numero_documento', $docNumero)->first();
                $clienteId = $clienteFisico?->id;
            }

            // 8. Crear registro de Pedido
            $pedido = Pedido::create([
                'codigo' => $nuevoCodigo,
                'cliente_web_id' => $datos['cliente_web_id'] ?? $carrito->cliente_web_id,
                'cliente_id' => $clienteId,
                'estado' => EstadoPedido::PENDIENTE_PAGO,
                'metodo_entrega' => $cotizacionEnvio['metodo'],
                'direccion_snapshot' => $datos['direccion_snapshot'] ?? null,
                'zona_envio_id' => $cotizacionEnvio['zona_id'],
                'cupon_id' => $cuponId,
                'subtotal' => $subtotalPedido,
                'costo_envio' => $costoEnvio,
                'descuento' => $descuentoCupon,
                'total' => $totalPedido,
                'tipo_comprobante' => $tipoComprobante,
                'documento_tipo' => $docTipo,
                'documento_numero' => $docNumero,
                'razon_social' => $datos['razon_social'] ?? null,
                'direccion_fiscal' => $datos['direccion_fiscal'] ?? null,
                'email_contacto' => trim((string) $datos['email_contacto']),
                'telefono_contacto' => $datos['telefono_contacto'] ?? null,
                'reserva_expira_en' => $reservaExpiraEn,
                'notas_cliente' => $datos['notas_cliente'] ?? null,
            ]);

            // 9. Crear líneas de pedido y pasar series a 'reservada' con movimiento de inventario
            foreach ($itemsParaGuardar as $it) {
                $pedidoItem = PedidoItem::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $it['producto']->id,
                    'descripcion' => $it['descripcion'],
                    'cantidad' => $it['cantidad'],
                    'precio_unitario' => $it['precio_unitario'],
                    'subtotal' => $it['subtotal'],
                ]);

                foreach ($it['series'] as $serie) {
                    PedidoItemSerie::create([
                        'pedido_item_id' => $pedidoItem->id,
                        'serie_id' => $serie->id,
                    ]);

                    $serie->update([
                        'estado' => EstadoSerie::RESERVADA,
                        'observaciones' => trim(($serie->observaciones ?? '')."\nReservada por Pedido {$pedido->codigo}"),
                    ]);

                    // Movimiento de inventario por reserva
                    $stockRestante = Serie::where('producto_id', $it['producto']->id)
                        ->where('estado', EstadoSerie::DISPONIBLE)
                        ->count();

                    $usuarioSistemaId = User::role('admin')->value('id') ?? User::value('id');

                    MovimientoInventario::create([
                        'producto_id' => $it['producto']->id,
                        'tipo' => TipoMovimiento::SALIDA,
                        'cantidad' => 1,
                        'stock_resultante' => $stockRestante,
                        'motivo' => "Reserva web por {$pedido->codigo}",
                        'user_id' => $usuarioSistemaId,
                    ]);
                }
            }

            // 10. Registrar evento inicial
            PedidoEvento::create([
                'pedido_id' => $pedido->id,
                'estado_anterior' => null,
                'estado_nuevo' => EstadoPedido::PENDIENTE_PAGO->value,
                'comentario' => "Pedido creado con reserva de stock válida hasta {$reservaExpiraEn->format('d/m/Y H:i')}.",
                'user_id' => null,
            ]);

            // 11. Vaciar carrito del cliente
            $this->carritoService->vaciar($carrito);

            return $pedido;
        });
    }

    /**
     * Cancelar un pedido liberando las series a disponible.
     */
    public function cancelar(Pedido $pedido, string $motivo, ?User $actor = null): Pedido
    {
        return DB::transaction(function () use ($pedido, $motivo, $actor) {
            $pedidoLock = Pedido::where('id', $pedido->id)->lockForUpdate()->firstOrFail();

            if (! in_array($pedidoLock->estado, [EstadoPedido::PENDIENTE_PAGO, EstadoPedido::PAGADO, EstadoPedido::PREPARANDO], true)) {
                throw new InvalidArgumentException("No se puede cancelar un pedido en estado '{$pedidoLock->estado->etiqueta()}'.");
            }

            $estadoAnterior = $pedidoLock->estado->value;

            // Liberar series si estaban reservadas
            $this->liberarSeriesReservadas($pedidoLock, "Cancelación de pedido {$pedidoLock->codigo}: {$motivo}");

            $pedidoLock->update([
                'estado' => EstadoPedido::CANCELADO,
                'cancelado_motivo' => $motivo,
                'reserva_expira_en' => null,
            ]);

            PedidoEvento::create([
                'pedido_id' => $pedidoLock->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => EstadoPedido::CANCELADO->value,
                'comentario' => "Pedido cancelado: {$motivo}",
                'user_id' => $actor?->id,
            ]);

            return $pedidoLock;
        });
    }

    /**
     * Expirar reservas vencidas (para ejecución periódica cada minuto).
     *
     * @return int Cantidad de pedidos expirados
     */
    public function expirarVencidos(): int
    {
        $vencidos = Pedido::reservasVencidas()->get();
        $contador = 0;

        foreach ($vencidos as $pedido) {
            DB::transaction(function () use ($pedido, &$contador) {
                $p = Pedido::where('id', $pedido->id)->lockForUpdate()->first();
                if ($p && $p->estaExpirado()) {
                    $this->liberarSeriesReservadas($p, "Expiración automática de reserva ({$p->codigo})");

                    $p->update([
                        'estado' => EstadoPedido::EXPIRADO,
                        'cancelado_motivo' => 'Reserva de stock vencida por falta de pago.',
                        'reserva_expira_en' => null,
                    ]);

                    PedidoEvento::create([
                        'pedido_id' => $p->id,
                        'estado_anterior' => EstadoPedido::PENDIENTE_PAGO->value,
                        'estado_nuevo' => EstadoPedido::EXPIRADO->value,
                        'comentario' => 'Reserva expirada automáticamente por superar el tiempo límite.',
                        'user_id' => null,
                    ]);

                    $contador++;
                }
            });
        }

        return $contador;
    }

    /**
     * Cambiar estado de un pedido validando transiciones permitidas.
     */
    public function cambiarEstado(Pedido $pedido, EstadoPedido $nuevoEstado, ?string $comentario = null, ?User $actor = null): Pedido
    {
        return DB::transaction(function () use ($pedido, $nuevoEstado, $comentario, $actor) {
            $p = Pedido::where('id', $pedido->id)->lockForUpdate()->firstOrFail();

            if (! $p->estado->puedePasarA($nuevoEstado)) {
                throw new InvalidArgumentException("Transición inválida: no se puede pasar de '{$p->estado->etiqueta()}' a '{$nuevoEstado->etiqueta()}'.");
            }

            $anterior = $p->estado->value;
            $p->update(['estado' => $nuevoEstado]);

            PedidoEvento::create([
                'pedido_id' => $p->id,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $nuevoEstado->value,
                'comentario' => $comentario ?? "Cambio de estado a {$nuevoEstado->etiqueta()}.",
                'user_id' => $actor?->id,
            ]);

            return $p;
        });
    }

    /**
     * Marcar pedido como enviado con courier y código de tracking.
     */
    public function marcarEnviado(Pedido $pedido, string $courier, string $trackingCodigo, ?User $actor = null): Pedido
    {
        return DB::transaction(function () use ($pedido, $courier, $trackingCodigo, $actor) {
            $p = Pedido::where('id', $pedido->id)->lockForUpdate()->firstOrFail();

            $p->update([
                'courier' => trim($courier),
                'tracking_codigo' => trim($trackingCodigo),
            ]);

            return $this->cambiarEstado(
                $p,
                EstadoPedido::ENVIADO,
                "Enviado por courier {$courier} con guía de tracking: {$trackingCodigo}.",
                $actor
            );
        });
    }

    /**
     * Marcar pedido como entregado al cliente final.
     */
    public function marcarEntregado(Pedido $pedido, ?User $actor = null): Pedido
    {
        return $this->cambiarEstado(
            $pedido,
            EstadoPedido::ENTREGADO,
            'Pedido entregado satisfactoriamente al destinatario.',
            $actor
        );
    }

    /**
     * Libera las series de un pedido devolviéndolas a estado 'disponible'.
     */
    protected function liberarSeriesReservadas(Pedido $pedido, string $motivo): void
    {
        $pedido->loadMissing(['items.itemSeries.serie']);
        $usuarioSistemaId = User::role('admin')->value('id') ?? User::value('id');

        foreach ($pedido->items as $item) {
            foreach ($item->itemSeries as $itemSerie) {
                $serie = $itemSerie->serie;
                if ($serie && $serie->estado === EstadoSerie::RESERVADA) {
                    $serie->update([
                        'estado' => EstadoSerie::DISPONIBLE,
                        'observaciones' => trim(($serie->observaciones ?? '')."\nLiberada de {$pedido->codigo}"),
                    ]);

                    $stockRestante = Serie::where('producto_id', $serie->producto_id)
                        ->where('estado', EstadoSerie::DISPONIBLE)
                        ->count();

                    MovimientoInventario::create([
                        'producto_id' => $serie->producto_id,
                        'tipo' => TipoMovimiento::INGRESO,
                        'cantidad' => 1,
                        'stock_resultante' => $stockRestante,
                        'motivo' => $motivo,
                        'user_id' => $usuarioSistemaId,
                    ]);
                }
            }
        }
    }
}
