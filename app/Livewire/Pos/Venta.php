<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Enums\EstadoOrdenServicio;
use App\Enums\EstadoSerie;
use App\Enums\MetodoPago;
use App\Enums\MotivoDevolucion;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDevolucion;
use App\Enums\TipoDocumentoCliente;
use App\Enums\TipoMovimientoCaja;
use App\Models\CajaSesion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use App\Models\Venta as VentaModel;
use App\Services\CajaService;
use App\Services\CambioService;
use App\Services\ClienteService;
use App\Services\DevolucionService;
use App\Services\GarantiaService;
use App\Services\InventarioService;
use App\Services\OrdenServicioService;
use App\Services\VentaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read float $subtotal
 * @property-read float $igv
 * @property-read float $descuentoTotal
 * @property-read float $total
 * @property-read float $totalPagos
 * @property-read float $montoPendiente
 * @property-read float $vueltoCalculado
 */
#[Layout('components.layouts.pos')]
class Venta extends Component
{
    // ==========================================
    // ESTADO DE SESIÓN DE CAJA
    // ==========================================
    public ?CajaSesion $cajaSesion = null;

    public bool $mostrarModalAbrirCaja = false;

    public string $montoAperturaInput = '0.00';

    public ?string $observacionesApertura = null;

    // Modal de movimientos de caja (ingreso / egreso manual)
    public bool $mostrarModalMovimiento = false;

    public string $tipoMovimientoInput = 'ingreso';

    public string $conceptoMovimientoInput = '';

    public string $montoMovimientoInput = '';

    public string $metodoPagoMovimientoInput = 'efectivo';

    // Modal de cierre de caja
    public bool $mostrarModalCierreCaja = false;

    public string $montoCierreRealInput = '';

    public ?string $observacionesCierre = null;

    public ?array $resumenCierre = null;

    public ?int $sesionIdCerrada = null;

    // ==========================================
    // BUSCADOR Y CATÁLOGO (IZQUIERDA)
    // ==========================================
    #[Url(as: 'q')]
    public string $busqueda = '';

    public ?int $categoriaSeleccionada = null;

    // Modal selector de series para producto que controla series
    public bool $mostrarModalSeries = false;

    public ?int $productoSeleccionadoId = null;

    public string $busquedaSerie = '';

    // ==========================================
    // CARRITO DE VENTA (DERECHA)
    // ==========================================
    /**
     * Array de líneas del carrito. Cada elemento:
     * [
     *   'id' => string (uuid único de línea),
     *   'producto_id' => int,
     *   'producto_nombre' => string,
     *   'producto_sku' => string,
     *   'controla_series' => bool,
     *   'serie_id' => ?int,
     *   'serie_numero' => ?string,
     *   'cantidad' => int,
     *   'precio_unitario' => float,
     *   'descuento' => float,
     *   'subtotal' => float
     * ]
     *
     * @var array<int, array<string, mixed>>
     */
    public array $carrito = [];

    // Selección de Cliente
    public int $clienteId;

    public string $busquedaCliente = '';

    public bool $mostrarModalNuevoCliente = false;

    public string $nuevoClienteTipo = 'DNI';

    public string $nuevoClienteNumero = '';

    public string $nuevoClienteNombre = '';

    public string $nuevoClienteDireccion = '';

    public string $nuevoClienteTelefono = '';

    public string $nuevoClienteEmail = '';

    // Tipo de Comprobante
    public string $tipoComprobante = 'boleta'; // boleta, factura, nota_venta

    // Descuento global adicional
    public string $descuentoGlobalInput = '0.00';

    // ==========================================
    // MODAL DE COBRO Y PAGOS MIXTOS
    // ==========================================
    public bool $mostrarModalCobro = false;

    public string $idempotencyKey = '';

    public bool $procesandoVenta = false;

    /**
     * @var array<int, array{metodo: string, monto: string, referencia: ?string}>
     */
    public array $pagos = [];

    public string $efectivoRecibido = '';

    // Pantalla de éxito tras registrar venta
    public ?VentaModel $ventaExitosa = null;

    // ==========================================
    // HISTORIAL DEL DÍA DEL VENDEDOR
    // ==========================================
    public bool $mostrarModalHistorial = false;

    public string $busquedaHistorial = '';

    // Mensajes flash locales
    public ?string $mensajeError = null;

    public ?string $mensajeExito = null;

    // ==========================================
    // FASE 3C: CONSULTA DE GARANTÍA EN POS
    // ==========================================
    public bool $mostrarModalGarantia = false;

    public string $busquedaGarantiaSerie = '';

    public ?array $resultadoGarantia = null;

    // ==========================================
    // FASE 3C: ASISTENTE DE DEVOLUCIÓN / CAMBIO
    // ==========================================
    public bool $mostrarModalDevolucion = false;

    public ?VentaModel $ventaDevolucion = null;

    public int $pasoDevolucion = 1; // 1: Items, 2: Motivo y estado fisico, 3: Tipo, 4: Cambio serie, 5: Confirmar

    public array $itemsDevolucion = [];

    public string $tipoDevolucionInput = 'devolucion_dinero'; // devolucion_dinero, cambio_producto, nota_credito

    public string $motivoDevolucionInput = 'defecto_fabrica';

    public string $detalleMotivoDevolucion = '';

    public string $metodoReembolsoDevolucion = 'efectivo';

    public string $serieNuevaCambioInput = '';

    public ?Serie $serieNuevaSeleccionada = null;

    public float $diferenciaCambio = 0.00;

    // ==========================================
    // FASE 3C: RECEPCIÓN Y ENTREGA SERVICIO TÉCNICO
    // ==========================================
    public bool $mostrarModalServicio = false;

    public string $tabServicio = 'recibir'; // 'recibir' o 'entregar'

    public string $servicioTipoInput = 'garantia'; // garantia o pago

    public string $servicioSerieInput = '';

    public string $servicioEquipoDescripcion = '';

    public string $servicioSerieExterna = '';

    public int $servicioClienteId = 0;

    public string $servicioFalla = '';

    public string $servicioEstadoRecepcion = 'Carcasa íntegra, con accesorios originales';

    public string $servicioFechaPromesa = '';

    public string $servicioEntregadoA = '';

    public function mount(CajaService $cajaService): void
    {
        $this->verificarCaja($cajaService);
        $this->inicializarCliente();
        $this->generarIdempotencyKey();
    }

    public function verificarCaja(CajaService $cajaService): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->cajaSesion = $cajaService->obtenerSesionActiva($user);

        if (! $this->cajaSesion) {
            $this->mostrarModalAbrirCaja = true;
        } else {
            $this->mostrarModalAbrirCaja = false;
        }
    }

    public function generarIdempotencyKey(): void
    {
        $this->idempotencyKey = (string) Str::uuid();
    }

    public function inicializarCliente(): void
    {
        $clienteGenerico = Cliente::where('tipo_documento', TipoDocumentoCliente::SIN_DOC->value)->first();
        if ($clienteGenerico) {
            $this->clienteId = $clienteGenerico->id;
        } else {
            $primerCliente = Cliente::first();
            $this->clienteId = $primerCliente?->id ?? 1;
        }
    }

    // =========================================================================
    // ACCIONES DE CAJA
    // =========================================================================
    public function abrirCaja(CajaService $cajaService): void
    {
        $this->limpiarMensajes();

        $this->validate([
            'montoAperturaInput' => ['required', 'numeric', 'min:0'],
        ], [
            'montoAperturaInput.required' => 'El monto de apertura es obligatorio.',
            'montoAperturaInput.numeric' => 'El monto debe ser un número válido.',
            'montoAperturaInput.min' => 'El monto no puede ser negativo.',
        ]);

        try {
            /** @var User $user */
            $user = Auth::user();
            $this->cajaSesion = $cajaService->abrir(
                $user,
                $this->montoAperturaInput,
                $this->observacionesApertura
            );

            $this->mostrarModalAbrirCaja = false;
            $this->mensajeExito = 'Caja abierta exitosamente con S/ '.number_format((float) $this->montoAperturaInput, 2);
        } catch (\Throwable $e) {
            $this->mensajeError = $e->getMessage();
        }
    }

    public function abrirModalMovimiento(): void
    {
        $this->limpiarMensajes();
        $this->conceptoMovimientoInput = '';
        $this->montoMovimientoInput = '';
        $this->tipoMovimientoInput = 'ingreso';
        $this->mostrarModalMovimiento = true;
    }

    public function registrarMovimientoCaja(CajaService $cajaService): void
    {
        $this->limpiarMensajes();

        $this->validate([
            'conceptoMovimientoInput' => ['required', 'string', 'min:3'],
            'montoMovimientoInput' => ['required', 'numeric', 'gt:0'],
        ], [
            'conceptoMovimientoInput.required' => 'El concepto o motivo es obligatorio.',
            'montoMovimientoInput.gt' => 'El monto debe ser mayor a 0.',
        ]);

        if (! $this->cajaSesion || ! $this->cajaSesion->estaAbierta()) {
            $this->mensajeError = 'No hay una caja abierta para registrar movimientos.';

            return;
        }

        try {
            /** @var User $user */
            $user = Auth::user();
            $tipo = $this->tipoMovimientoInput === 'egreso' ? TipoMovimientoCaja::EGRESO : TipoMovimientoCaja::INGRESO;
            $metodo = MetodoPago::tryFrom($this->metodoPagoMovimientoInput) ?? MetodoPago::EFECTIVO;

            $cajaService->registrarMovimiento(
                cajaSesion: $this->cajaSesion,
                tipo: $tipo,
                concepto: $this->conceptoMovimientoInput,
                monto: $this->montoMovimientoInput,
                metodoPago: $metodo,
                usuario: $user
            );

            $this->mostrarModalMovimiento = false;
            $this->mensajeExito = "Movimiento de {$tipo->etiqueta()} registrado correctamente.";
        } catch (\Throwable $e) {
            $this->mensajeError = $e->getMessage();
        }
    }

    public function abrirModalCierre(CajaService $cajaService): void
    {
        $this->limpiarMensajes();
        if (! $this->cajaSesion) {
            return;
        }

        $this->resumenCierre = $cajaService->resumen($this->cajaSesion);
        $this->montoCierreRealInput = (string) ($this->resumenCierre['monto_cierre_esperado'] ?? '0.00');
        $this->observacionesCierre = null;
        $this->mostrarModalCierreCaja = true;
    }

    public function cerrarCaja(CajaService $cajaService): void
    {
        $this->limpiarMensajes();

        $this->validate([
            'montoCierreRealInput' => ['required', 'numeric', 'min:0'],
        ], [
            'montoCierreRealInput.required' => 'Debe ingresar el monto real contado en efectivo.',
            'montoCierreRealInput.min' => 'El monto no puede ser negativo.',
        ]);

        if (! $this->cajaSesion) {
            return;
        }

        try {
            /** @var User $user */
            $user = Auth::user();
            $sesionCerrada = $cajaService->cerrar(
                cajaSesion: $this->cajaSesion,
                montoReal: $this->montoCierreRealInput,
                usuario: $user,
                observaciones: $this->observacionesCierre
            );

            $this->sesionIdCerrada = $sesionCerrada->id;
            $this->cajaSesion = null;
            $this->mostrarModalCierreCaja = false;
            $this->mostrarModalAbrirCaja = true;
            $this->mensajeExito = "Caja #{$sesionCerrada->id} cerrada correctamente. Diferencia: S/ ".number_format((float) $sesionCerrada->diferencia, 2);
        } catch (\Throwable $e) {
            $this->mensajeError = $e->getMessage();
        }
    }

    // =========================================================================
    // ESCANEO Y AGREGADO AL CARRITO (IZQUIERDA)
    // =========================================================================
    public function procesarBusquedaEnter(InventarioService $inventarioService): void
    {
        $termino = trim($this->busqueda);
        if ($termino === '') {
            return;
        }

        $this->limpiarMensajes();

        // 1. Verificar si coincide exactamente con un número de serie existente
        $serie = $inventarioService->buscarSerie($termino);
        if ($serie) {
            if ($serie->estado !== EstadoSerie::DISPONIBLE) {
                $this->mensajeError = "La serie '{$serie->numero_serie}' no está disponible (Estado: {$serie->estado->etiqueta()}).";

                return;
            }

            $this->agregarSerieAlCarrito($serie);
            $this->busqueda = '';

            return;
        }

        // 2. Verificar si coincide con SKU exacto de un producto
        $producto = Producto::where('sku', strtoupper($termino))->where('activo', true)->first();
        if ($producto) {
            $this->seleccionarProducto($producto->id);
            $this->busqueda = '';

            return;
        }

        // 3. Si no hay match directo, dejamos el término para filtrar la cuadrícula
    }

    public function seleccionarProducto(int $productoId): void
    {
        $this->limpiarMensajes();
        $producto = Producto::find($productoId);
        if (! $producto || ! $producto->activo) {
            $this->mensajeError = 'El producto no se encuentra disponible.';

            return;
        }

        if ($producto->controla_series) {
            $this->productoSeleccionadoId = $producto->id;
            $this->busquedaSerie = '';
            $this->mostrarModalSeries = true;
        } else {
            $this->agregarProductoSinSerieAlCarrito($producto);
        }
    }

    public function agregarSerieAlCarrito(Serie $serie): void
    {
        // Validar si ya está en el carrito
        foreach ($this->carrito as $item) {
            if (($item['serie_id'] ?? null) === $serie->id) {
                $this->mensajeError = "La serie '{$serie->numero_serie}' ya se encuentra agregada en el carrito.";

                return;
            }
        }

        $producto = $serie->producto;
        $precio = (float) $producto->precio_venta;

        $this->carrito[] = [
            'id' => (string) Str::uuid(),
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'producto_sku' => $producto->sku,
            'controla_series' => true,
            'serie_id' => $serie->id,
            'serie_numero' => $serie->numero_serie,
            'cantidad' => 1,
            'precio_unitario' => $precio,
            'descuento' => 0.0,
            'subtotal' => $precio,
        ];

        $this->mostrarModalSeries = false;
        $this->productoSeleccionadoId = null;
    }

    public function agregarProductoSinSerieAlCarrito(Producto $producto, int $cantidad = 1): void
    {
        // Verificar stock actual
        $stock = app(InventarioService::class)->stockDisponible($producto);

        // Buscar si ya existe en el carrito
        foreach ($this->carrito as $index => $item) {
            if ($item['producto_id'] === $producto->id && ! $item['controla_series']) {
                $nuevaCantidad = $item['cantidad'] + $cantidad;
                if ($nuevaCantidad > $stock) {
                    $this->mensajeError = "No hay suficiente stock disponible para '{$producto->nombre}' (Disponible: {$stock}).";

                    return;
                }

                $this->carrito[$index]['cantidad'] = $nuevaCantidad;
                $this->recalcularLinea($index);

                return;
            }
        }

        if ($cantidad > $stock) {
            $this->mensajeError = "No hay suficiente stock disponible para '{$producto->nombre}' (Disponible: {$stock}).";

            return;
        }

        $precio = (float) $producto->precio_venta;
        $subtotal = round($precio * $cantidad, 2);

        $this->carrito[] = [
            'id' => (string) Str::uuid(),
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'producto_sku' => $producto->sku,
            'controla_series' => false,
            'serie_id' => null,
            'serie_numero' => null,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'descuento' => 0.0,
            'subtotal' => $subtotal,
        ];
    }

    public function actualizarCantidad(int $index, int $delta): void
    {
        if (! isset($this->carrito[$index])) {
            return;
        }

        $item = $this->carrito[$index];
        if ($item['controla_series']) {
            return; // Productos con series siempre son cantidad 1
        }

        $producto = Producto::find($item['producto_id']);
        if (! $producto) {
            return;
        }

        $stock = app(InventarioService::class)->stockDisponible($producto);
        $nuevaCantidad = $item['cantidad'] + $delta;

        if ($nuevaCantidad <= 0) {
            $this->quitarLinea($index);

            return;
        }

        if ($nuevaCantidad > $stock) {
            $this->mensajeError = "Stock insuficiente para '{$producto->nombre}' (Disponible: {$stock}).";

            return;
        }

        $this->carrito[$index]['cantidad'] = $nuevaCantidad;
        $this->recalcularLinea($index);
    }

    public function actualizarDescuentoLinea(int $index, string|float $descuento): void
    {
        if (! isset($this->carrito[$index])) {
            return;
        }

        $desc = max(0.0, round((float) $descuento, 2));
        $precio = (float) $this->carrito[$index]['precio_unitario'];

        // Vendedor máximo 5%
        /** @var User $user */
        $user = Auth::user();
        if (! $user->hasRole('admin') && $precio > 0) {
            $porcentaje = ($desc / $precio) * 100;
            if ($porcentaje > 5.0) {
                $this->mensajeError = 'El descuento máximo para vendedores es del 5%.';
                $desc = round($precio * 0.05, 2);
            }
        }

        $this->carrito[$index]['descuento'] = $desc;
        $this->recalcularLinea($index);
    }

    protected function recalcularLinea(int $index): void
    {
        $item = $this->carrito[$index];
        $precioFinal = max(0.0, (float) $item['precio_unitario'] - (float) $item['descuento']);
        $this->carrito[$index]['subtotal'] = round($precioFinal * (int) $item['cantidad'], 2);
    }

    public function quitarLinea(int $index): void
    {
        unset($this->carrito[$index]);
        $this->carrito = array_values($this->carrito);
    }

    public function limpiarCarrito(): void
    {
        $this->carrito = [];
        $this->descuentoGlobalInput = '0.00';
        $this->pagos = [];
        $this->efectivoRecibido = '';
        $this->generarIdempotencyKey();
        $this->limpiarMensajes();
    }

    // =========================================================================
    // TOTALES COMPUTADOS
    // =========================================================================
    public function getSubtotalProperty(): float
    {
        $totalNeto = $this->total;

        return round($totalNeto / 1.18, 2);
    }

    public function getIgvProperty(): float
    {
        $totalNeto = $this->total;

        return round($totalNeto - $this->subtotal, 2);
    }

    public function getDescuentoTotalProperty(): float
    {
        $descuentoLineas = 0.0;
        foreach ($this->carrito as $item) {
            $descuentoLineas += ((float) $item['descuento']) * ((int) $item['cantidad']);
        }
        $descuentoGlobal = max(0.0, round((float) $this->descuentoGlobalInput, 2));

        return round($descuentoLineas + $descuentoGlobal, 2);
    }

    public function getTotalProperty(): float
    {
        $bruto = 0.0;
        foreach ($this->carrito as $item) {
            $bruto += (float) $item['subtotal'];
        }
        $descuentoGlobal = max(0.0, round((float) $this->descuentoGlobalInput, 2));

        return round(max(0.0, $bruto - $descuentoGlobal), 2);
    }

    // =========================================================================
    // MODAL DE COBRO Y PAGOS MIXTOS (4)
    // =========================================================================
    public function abrirModalCobro(): void
    {
        $this->limpiarMensajes();

        if (empty($this->carrito)) {
            $this->mensajeError = 'El carrito está vacío. Agregue productos antes de cobrar.';

            return;
        }

        if (! $this->cajaSesion || ! $this->cajaSesion->estaAbierta()) {
            $this->mensajeError = 'Debe abrir una sesión de caja antes de poder cobrar.';
            $this->mostrarModalAbrirCaja = true;

            return;
        }

        // Validación inicial de cliente y comprobante
        $cliente = Cliente::find($this->clienteId);
        if ($this->tipoComprobante === 'factura') {
            if (! $cliente || $cliente->tipo_documento !== TipoDocumentoCliente::RUC) {
                $this->mensajeError = 'La Factura exige seleccionar un cliente con RUC válido.';

                return;
            }
        }

        if ($this->tipoComprobante === 'boleta' && $this->total >= 700.0) {
            if (! $cliente || $cliente->tipo_documento === TipoDocumentoCliente::SIN_DOC) {
                $this->mensajeError = 'Boletas ≥ S/ 700.00 exigen identificar al cliente con DNI u otro documento.';

                return;
            }
        }

        // Inicializar método de pago en efectivo por el total por defecto
        $this->pagos = [
            [
                'metodo' => MetodoPago::EFECTIVO->value,
                'monto' => (string) $this->total,
                'referencia' => null,
            ],
        ];
        $this->efectivoRecibido = (string) $this->total;

        $this->mostrarModalCobro = true;
    }

    public function agregarMetodoPago(): void
    {
        $pendiente = $this->montoPendiente;
        $montoInicial = $pendiente > 0 ? (string) $pendiente : '0.00';

        $this->pagos[] = [
            'metodo' => MetodoPago::YAPE->value,
            'monto' => $montoInicial,
            'referencia' => null,
        ];
    }

    public function eliminarMetodoPago(int $index): void
    {
        if (count($this->pagos) > 1) {
            unset($this->pagos[$index]);
            $this->pagos = array_values($this->pagos);
        }
    }

    public function getTotalPagosProperty(): float
    {
        $suma = 0.0;
        foreach ($this->pagos as $p) {
            $suma += max(0.0, round((float) ($p['monto'] ?? 0), 2));
        }

        return round($suma, 2);
    }

    public function getMontoPendienteProperty(): float
    {
        return round(max(0.0, $this->total - $this->totalPagos), 2);
    }

    public function getVueltoCalculadoProperty(): float
    {
        $totalEfectivo = 0.0;
        foreach ($this->pagos as $p) {
            if ($p['metodo'] === MetodoPago::EFECTIVO->value) {
                $totalEfectivo += (float) ($p['monto'] ?? 0);
            }
        }

        $recibido = max(0.0, round((float) $this->efectivoRecibido, 2));
        if ($recibido > $totalEfectivo) {
            return round($recibido - $totalEfectivo, 2);
        }

        return 0.0;
    }

    public function confirmarVenta(VentaService $ventaService): void
    {
        $this->limpiarMensajes();

        if ($this->procesandoVenta) {
            return;
        }

        $this->procesandoVenta = true;

        // Validar que los pagos cubran el total
        if ($this->totalPagos < $this->total) {
            $faltante = round($this->total - $this->totalPagos, 2);
            $this->mensajeError = "Los métodos de pago no cubren el total de la venta. Faltan S/ {$faltante}.";
            $this->procesandoVenta = false;

            return;
        }

        try {
            /** @var User $user */
            $user = Auth::user();

            $datos = [
                'cliente_id' => $this->clienteId,
                'tipo_comprobante' => TipoComprobanteVenta::from($this->tipoComprobante),
                'descuento_global' => (float) $this->descuentoGlobalInput,
            ];

            $items = array_map(function ($item) {
                return [
                    'producto_id' => (int) $item['producto_id'],
                    'serie_id' => $item['serie_id'] ? (int) $item['serie_id'] : null,
                    'cantidad' => (int) $item['cantidad'],
                    'precio_unitario' => (float) $item['precio_unitario'],
                    'descuento' => (float) $item['descuento'],
                ];
            }, $this->carrito);

            $pagosArray = array_map(function ($p) {
                return [
                    'metodo' => MetodoPago::from($p['metodo']),
                    'monto' => (float) $p['monto'],
                    'referencia' => $p['referencia'] ?? null,
                ];
            }, $this->pagos);

            // Registro en base de datos mediante Service
            $venta = $ventaService->registrar(
                datos: $datos,
                items: $items,
                pagos: $pagosArray,
                vendedor: $user
            );

            $this->ventaExitosa = $venta;
            $this->mostrarModalCobro = false;
            $this->limpiarCarrito();
            $this->mensajeExito = "¡Venta {$venta->codigo} registrada exitosamente!";
        } catch (\Throwable $e) {
            // No pierde el carrito si falla
            $this->mensajeError = $e->getMessage();
        } finally {
            $this->procesandoVenta = false;
        }
    }

    // =========================================================================
    // CREACIÓN RÁPIDA DE CLIENTE
    // =========================================================================
    public function crearClienteRapido(ClienteService $clienteService): void
    {
        $this->limpiarMensajes();

        $this->validate([
            'nuevoClienteNombre' => ['required', 'string', 'min:3'],
            'nuevoClienteNumero' => ['nullable', 'string'],
        ], [
            'nuevoClienteNombre.required' => 'El nombre o razón social es obligatorio.',
        ]);

        try {
            $tipoDoc = TipoDocumentoCliente::from($this->nuevoClienteTipo);
            $cliente = $clienteService->crear([
                'tipo_documento' => $tipoDoc,
                'numero_documento' => $this->nuevoClienteNumero !== '' ? $this->nuevoClienteNumero : null,
                'nombre_razon_social' => trim($this->nuevoClienteNombre),
                'direccion' => trim($this->nuevoClienteDireccion) !== '' ? trim($this->nuevoClienteDireccion) : null,
                'telefono' => trim($this->nuevoClienteTelefono) !== '' ? trim($this->nuevoClienteTelefono) : null,
                'email' => trim($this->nuevoClienteEmail) !== '' ? trim($this->nuevoClienteEmail) : null,
                'activo' => true,
            ]);

            $this->clienteId = $cliente->id;
            $this->mostrarModalNuevoCliente = false;
            $this->mensajeExito = "Cliente '{$cliente->nombre_razon_social}' creado y seleccionado.";

            // Limpiar formulario modal
            $this->nuevoClienteNumero = '';
            $this->nuevoClienteNombre = '';
            $this->nuevoClienteDireccion = '';
            $this->nuevoClienteTelefono = '';
            $this->nuevoClienteEmail = '';
        } catch (\Throwable $e) {
            $this->mensajeError = $e->getMessage();
        }
    }

    public function seleccionarClientesVarios(): void
    {
        $this->inicializarCliente();
    }

    public function limpiarMensajes(): void
    {
        $this->mensajeError = null;
        $this->mensajeExito = null;
    }

    // =========================================================================
    // FASE 3C: MÉTODOS DE CONSULTA DE GARANTÍA EN POS
    // =========================================================================
    public function abrirModalGarantia(): void
    {
        $this->limpiarMensajes();
        $this->busquedaGarantiaSerie = '';
        $this->resultadoGarantia = null;
        $this->mostrarModalGarantia = true;
    }

    public function consultarGarantiaEnPos(GarantiaService $garantiaService): void
    {
        $this->limpiarMensajes();
        $sn = trim($this->busquedaGarantiaSerie);
        if ($sn === '') {
            $this->mensajeError = 'Ingrese un número de serie para consultar.';

            return;
        }

        $res = $garantiaService->consultarPorSerie($sn);
        if (! $res['encontrado']) {
            $this->mensajeError = "No se encontró ningún registro para la serie '{$sn}'.";
            $this->resultadoGarantia = null;

            return;
        }

        $this->resultadoGarantia = $res;
        $this->mostrarModalGarantia = true;
    }

    public function iniciarDevolucionDesdeGarantia(): void
    {
        if (! $this->resultadoGarantia || empty($this->resultadoGarantia['venta_codigo'])) {
            $this->mensajeError = 'No hay una venta asociada a esta serie para procesar devolución.';

            return;
        }

        $venta = VentaModel::where('codigo', $this->resultadoGarantia['venta_codigo'])->first();
        if (! $venta) {
            $this->mensajeError = 'No se encontró la venta de origen.';

            return;
        }

        $this->mostrarModalGarantia = false;
        $this->abrirAsistenteDevolucion($venta->id);
    }

    public function recibirServicioDesdeGarantia(): void
    {
        if (! $this->resultadoGarantia) {
            return;
        }

        $this->mostrarModalGarantia = false;
        $this->servicioSerieInput = $this->resultadoGarantia['serie'];
        $this->servicioTipoInput = ($this->resultadoGarantia['vigente'] ?? false) ? 'garantia' : 'pago';
        $this->servicioClienteId = $this->clienteId;
        $this->servicioFalla = '';
        $this->tabServicio = 'recibir';
        $this->mostrarModalServicio = true;
    }

    // =========================================================================
    // FASE 3C: ASISTENTE DE DEVOLUCIONES Y CAMBIOS EN POS
    // =========================================================================
    public function abrirAsistenteDevolucion(int $ventaId): void
    {
        $this->limpiarMensajes();
        $venta = VentaModel::with(['detalles.producto', 'detalles.serie', 'cliente'])->find($ventaId);
        if (! $venta) {
            $this->mensajeError = 'Venta no encontrada.';

            return;
        }

        if ($venta->estado->value !== 'completada') {
            $this->mensajeError = 'Solo se pueden procesar devoluciones sobre ventas completadas.';

            return;
        }

        $this->ventaDevolucion = $venta;
        $this->pasoDevolucion = 1;
        $this->itemsDevolucion = [];
        $this->tipoDevolucionInput = 'devolucion_dinero';
        $this->motivoDevolucionInput = 'defecto_fabrica';
        $this->detalleMotivoDevolucion = '';
        $this->metodoReembolsoDevolucion = 'efectivo';
        $this->serieNuevaCambioInput = '';
        $this->serieNuevaSeleccionada = null;
        $this->diferenciaCambio = 0.00;

        $devolucionService = app(DevolucionService::class);

        // Pre-cargar ítems con su saldo devolvible
        foreach ($venta->detalles as $det) {
            $saldo = $devolucionService->saldoDevolvible($det);
            if ($saldo['cantidad_disponible'] > 0) {
                $this->itemsDevolucion[$det->id] = [
                    'seleccionado' => false,
                    'venta_detalle_id' => $det->id,
                    'producto_id' => $det->producto_id,
                    'producto_nombre' => $det->producto->nombre,
                    'serie_id' => $det->serie_id,
                    'serie_numero' => $det->serie?->numero_serie,
                    'cantidad_disponible' => $saldo['cantidad_disponible'],
                    'monto_disponible' => $saldo['monto_disponible'],
                    'cantidad_devolver' => 1,
                    'monto_devolver' => $saldo['monto_disponible'],
                    'estado_fisico' => 'nuevo_sellado',
                    'destino_serie' => 'disponible',
                ];
            }
        }

        $this->mostrarModalHistorial = false;
        $this->mostrarModalDevolucion = true;
    }

    public function avanzarPasoDevolucion(): void
    {
        $this->limpiarMensajes();

        if ($this->pasoDevolucion === 1) {
            $seleccionados = collect($this->itemsDevolucion)->where('seleccionado', true);
            if ($seleccionados->isEmpty()) {
                $this->mensajeError = 'Debe seleccionar al menos un producto o serie para devolver.';

                return;
            }
            $this->pasoDevolucion = 2;
        } elseif ($this->pasoDevolucion === 2) {
            if (empty(trim($this->detalleMotivoDevolucion))) {
                $this->mensajeError = 'Debe indicar el detalle o motivo de la devolución.';

                return;
            }
            $this->pasoDevolucion = 3;
        } elseif ($this->pasoDevolucion === 3) {
            if ($this->tipoDevolucionInput === 'cambio_producto') {
                $this->pasoDevolucion = 4;
            } else {
                $this->pasoDevolucion = 5;
            }
        } elseif ($this->pasoDevolucion === 4) {
            if (! $this->serieNuevaSeleccionada) {
                $this->mensajeError = 'Debe seleccionar o escanear una serie nueva disponible para el cambio.';

                return;
            }
            $this->pasoDevolucion = 5;
        }
    }

    public function retrocederPasoDevolucion(): void
    {
        if ($this->pasoDevolucion > 1) {
            $this->pasoDevolucion--;
        }
    }

    public function buscarSerieNuevaParaCambio(): void
    {
        $this->limpiarMensajes();
        $sn = strtoupper(trim($this->serieNuevaCambioInput));
        if ($sn === '') {
            $this->mensajeError = 'Ingrese el número de serie para el cambio.';

            return;
        }

        $serie = Serie::where('numero_serie', $sn)->where('estado', EstadoSerie::DISPONIBLE)->with('producto')->first();
        if (! $serie) {
            $this->mensajeError = "La serie '{$sn}' no existe o no está en estado disponible.";
            $this->serieNuevaSeleccionada = null;

            return;
        }

        $this->serieNuevaSeleccionada = $serie;

        // Calcular diferencia con el primer ítem devuelto
        $itemDev = collect($this->itemsDevolucion)->firstWhere('seleccionado', true);
        if ($itemDev) {
            $precioOriginal = (float) $itemDev['monto_devolver'];
            $precioNuevo = (float) $serie->producto->precio_venta;
            $this->diferenciaCambio = round($precioNuevo - $precioOriginal, 2);
        }
    }

    public function procesarDevolucion(DevolucionService $devolucionService): void
    {
        $this->limpiarMensajes();
        $user = Auth::user();

        $itemsParaEnviar = [];
        foreach ($this->itemsDevolucion as $it) {
            if (! empty($it['seleccionado'])) {
                $itemsParaEnviar[] = [
                    'venta_detalle_id' => $it['venta_detalle_id'],
                    'serie_id' => $it['serie_id'],
                    'cantidad' => (int) $it['cantidad_devolver'],
                    'monto_devuelto' => (float) $it['monto_devolver'],
                    'estado_fisico' => $it['estado_fisico'],
                    'destino_serie' => $it['destino_serie'],
                ];
            }
        }

        try {
            $tipoEnum = TipoDevolucion::from($this->tipoDevolucionInput);
            $motivoEnum = MotivoDevolucion::from($this->motivoDevolucionInput);

            $devolucion = $devolucionService->solicitar(
                venta: $this->ventaDevolucion,
                items: $itemsParaEnviar,
                tipo: $tipoEnum,
                motivo: $motivoEnum,
                detalleMotivo: trim($this->detalleMotivoDevolucion),
                solicitante: $user,
                metodoReembolso: $this->metodoReembolsoDevolucion,
                aprobacionExcepcionalAdmin: $user->hasRole('admin')
            );

            // Si el usuario es administrador y está completando directamente
            if ($user->hasRole('admin')) {
                if ($this->tipoDevolucionInput === 'cambio_producto' && $this->serieNuevaSeleccionada) {
                    $itemOriginal = collect($itemsParaEnviar)->first();
                    $serieOriginal = Serie::find($itemOriginal['serie_id']);
                    if ($serieOriginal) {
                        app(CambioService::class)->cambiarSerie(
                            $devolucion,
                            $serieOriginal,
                            $this->serieNuevaSeleccionada,
                            $user
                        );
                    }
                }

                $devolucionService->completar($devolucion, $user);
                $this->mensajeExito = "Devolución {$devolucion->codigo} completada exitosamente.";
            } else {
                $this->mensajeExito = "Solicitud de devolución {$devolucion->codigo} enviada para aprobación de gerencia.";
            }

            $this->mostrarModalDevolucion = false;
        } catch (\Throwable $e) {
            $this->mensajeError = $e->getMessage();
        }
    }

    // =========================================================================
    // FASE 3C: SERVICIO TÉCNICO EN POS (RECEPCIÓN RÁPIDA Y ENTREGA)
    // =========================================================================
    public function abrirModalServicio(): void
    {
        $this->limpiarMensajes();
        $this->tabServicio = 'recibir';
        $this->servicioSerieInput = '';
        $this->servicioEquipoDescripcion = '';
        $this->servicioSerieExterna = '';
        $this->servicioClienteId = $this->clienteId;
        $this->servicioFalla = '';
        $this->servicioEstadoRecepcion = 'Carcasa íntegra, con cargador/accesorios originales';
        $this->servicioFechaPromesa = now()->addDays(2)->format('Y-m-d');
        $this->mostrarModalServicio = true;
    }

    public function registrarRecepcionServicio(OrdenServicioService $ordenService): void
    {
        $this->limpiarMensajes();
        $user = Auth::user();

        if (empty(trim($this->servicioFalla))) {
            $this->mensajeError = 'Debe ingresar la falla reportada por el cliente.';

            return;
        }

        $serieId = null;
        if (! empty(trim($this->servicioSerieInput))) {
            $serie = Serie::where('numero_serie', strtoupper(trim($this->servicioSerieInput)))->first();
            $serieId = $serie?->id;
        }

        try {
            $orden = $ordenService->recibir([
                'cliente_id' => $this->servicioClienteId ?: $this->clienteId,
                'serie_id' => $serieId,
                'equipo_descripcion' => $this->servicioEquipoDescripcion ?: null,
                'serie_externa' => $this->servicioSerieExterna ?: null,
                'falla_reportada' => trim($this->servicioFalla),
                'estado_recepcion' => trim($this->servicioEstadoRecepcion),
                'fecha_promesa' => $this->servicioFechaPromesa ?: null,
            ], $user);

            $this->mensajeExito = "Orden de Servicio {$orden->codigo} creada exitosamente.";
            $this->mostrarModalServicio = false;
        } catch (\Throwable $e) {
            $this->mensajeError = $e->getMessage();
        }
    }

    public function entregarEquipoEnPos(int $ordenId, OrdenServicioService $ordenService): void
    {
        $this->limpiarMensajes();
        $user = Auth::user();
        $orden = OrdenServicio::find($ordenId);
        if (! $orden) {
            $this->mensajeError = 'Orden no encontrada.';

            return;
        }

        $persona = trim($this->servicioEntregadoA) !== '' ? trim($this->servicioEntregadoA) : $orden->cliente->nombre_razon_social;

        try {
            $ordenService->entregar($orden, $persona, $user);
            $this->mensajeExito = "Equipo de la orden {$orden->codigo} entregado a {$persona}.";
            $this->servicioEntregadoA = '';
        } catch (\Throwable $e) {
            $this->mensajeError = $e->getMessage();
        }
    }

    // RENDERIZADO
    // =========================================================================
    public function render(): View
    {
        $query = Producto::query()
            ->where('activo', true)
            ->with(['categoria', 'marca']);

        if ($this->categoriaSeleccionada) {
            $query->where('categoria_id', $this->categoriaSeleccionada);
        }

        if (trim($this->busqueda) !== '') {
            $termino = trim($this->busqueda);
            $query->where(function ($q) use ($termino) {
                $q->where('nombre', 'ilike', "%{$termino}%")
                    ->orWhere('sku', 'ilike', "%{$termino}%")
                    ->orWhere('modelo', 'ilike', "%{$termino}%");
            });
        }

        $productos = $query->orderBy('nombre')->paginate(16);

        $categorias = Categoria::where('activo', true)->orderBy('nombre')->get();

        // Series disponibles si se abrió el modal de series
        $seriesDisponibles = collect();
        if ($this->productoSeleccionadoId) {
            $sQuery = Serie::where('producto_id', $this->productoSeleccionadoId)
                ->where('estado', EstadoSerie::DISPONIBLE);

            if (trim($this->busquedaSerie) !== '') {
                $sQuery->where('numero_serie', 'ilike', '%'.trim($this->busquedaSerie).'%');
            }

            $seriesDisponibles = $sQuery->orderBy('numero_serie')->limit(50)->get();
        }

        // Clientes para selector
        $clientes = Cliente::query()->where('activo', true);
        if (trim($this->busquedaCliente) !== '') {
            $clientes->buscar(trim($this->busquedaCliente));
        }
        $clientesList = $clientes->orderBy('nombre_razon_social')->limit(20)->get();

        // Ventas del día del vendedor (para historial)
        /** @var User $user */
        $user = Auth::user();
        $ventasDelDia = collect();
        if ($this->mostrarModalHistorial) {
            $hQuery = VentaModel::where('vendedor_id', $user->id)
                ->whereDate('created_at', now())
                ->with(['cliente', 'detalles.producto', 'pagos'])
                ->orderBy('id', 'desc');

            if (trim($this->busquedaHistorial) !== '') {
                $term = trim($this->busquedaHistorial);
                $hQuery->where(function ($q) use ($term) {
                    $q->where('codigo', 'ilike', "%{$term}%")
                        ->orWhereHas('cliente', fn ($c) => $c->where('nombre_razon_social', 'ilike', "%{$term}%"));
                });
            }

            $ventasDelDia = $hQuery->get();
        }

        // Órdenes listas para entregar en el POS
        $ordenesParaEntregar = collect();
        if ($this->mostrarModalServicio && $this->tabServicio === 'entregar') {
            $ordenesParaEntregar = OrdenServicio::whereIn('estado', [
                EstadoOrdenServicio::REPARADO->value,
                EstadoOrdenServicio::SIN_SOLUCION->value,
            ])
                ->with(['cliente', 'producto', 'serie'])
                ->latest('id')
                ->get();
        }

        return view('livewire.pos.venta', [
            'productos' => $productos,
            'categorias' => $categorias,
            'seriesDisponibles' => $seriesDisponibles,
            'clientesList' => $clientesList,
            'ventasDelDia' => $ventasDelDia,
            'clienteActual' => Cliente::find($this->clienteId),
            'ordenesParaEntregar' => $ordenesParaEntregar,
        ]);
    }
}
