<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\NotificadorWhatsappServicio;
use App\Enums\EstadoCaja;
use App\Enums\EstadoGarantia;
use App\Enums\EstadoOrdenServicio;
use App\Enums\EstadoSerie;
use App\Enums\MetodoPago;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoMovimiento;
use App\Enums\TipoOrdenServicio;
use App\Jobs\NotificarOrdenReparadaEmailJob;
use App\Models\CajaSesion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Garantia;
use App\Models\Marca;
use App\Models\MovimientoInventario;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioEvento;
use App\Models\OrdenServicioFoto;
use App\Models\OrdenServicioRepuesto;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OrdenServicioService
{
    public function __construct(
        protected GarantiaService $garantiaService,
        protected InventarioService $inventarioService,
        protected VentaService $ventaService,
        protected DevolucionService $devolucionService,
        protected ?NotificadorWhatsappServicio $whatsappNotifier = null
    ) {}

    /**
     * Recibir un equipo para servicio técnico (por garantía o de pago).
     *
     * @param array{
     *     cliente_id: int,
     *     falla_reportada: string,
     *     estado_recepcion: string,
     *     serie_id?: int|null,
     *     producto_id?: int|null,
     *     equipo_descripcion?: string|null,
     *     serie_externa?: string|null,
     *     tipo?: string|TipoOrdenServicio|null,
     *     motivo_forzado?: string|null,
     *     fecha_promesa?: string|null,
     *     fotos?: array<int, array{ruta: string, descripcion?: string|null, momento?: string}>
     * } $datos
     */
    public function recibir(array $datos, User $usuario): OrdenServicio
    {
        if (empty($datos['cliente_id'])) {
            throw new InvalidArgumentException('El cliente es obligatorio para recibir una orden de servicio.');
        }

        if (empty(trim($datos['falla_reportada'] ?? ''))) {
            throw new InvalidArgumentException('Debe detallar la falla reportada por el cliente.');
        }

        if (empty(trim($datos['estado_recepcion'] ?? ''))) {
            throw new InvalidArgumentException('Debe detallar el estado físico de recepción (golpes, accesorios, etc.).');
        }

        return DB::transaction(function () use ($datos, $usuario) {
            $cliente = Cliente::findOrFail($datos['cliente_id']);
            if (! $cliente->activo) {
                throw new InvalidArgumentException('El cliente seleccionado se encuentra inactivo.');
            }

            $serie = null;
            $producto = null;
            $garantia = null;
            $tipo = null;

            if (! empty($datos['serie_id'])) {
                $serie = Serie::where('id', $datos['serie_id'])->lockForUpdate()->firstOrFail();
                $producto = $serie->producto;

                // Buscar garantía vigente de la serie
                $garantia = Garantia::where('serie_id', $serie->id)
                    ->where('estado', EstadoGarantia::VIGENTE)
                    ->latest('id')
                    ->first();

                $esVigente = ($garantia !== null && $garantia->esVigente());
                $tipo = $esVigente ? TipoOrdenServicio::GARANTIA : TipoOrdenServicio::PAGO;
            } elseif (! empty($datos['producto_id'])) {
                $producto = Producto::findOrFail($datos['producto_id']);
                $tipo = TipoOrdenServicio::PAGO;
            } else {
                if (empty(trim($datos['equipo_descripcion'] ?? ''))) {
                    throw new InvalidArgumentException('Para equipos no registrados en catálogo debe indicar la descripción del equipo.');
                }
                $tipo = TipoOrdenServicio::PAGO;
            }

            // Permitir forzar el tipo si el usuario tiene permiso (o rol admin)
            if (! empty($datos['tipo'])) {
                $tipoDeseado = $datos['tipo'] instanceof TipoOrdenServicio ? $datos['tipo'] : TipoOrdenServicio::from($datos['tipo']);
                if ($tipoDeseado !== $tipo) {
                    if (! $usuario->can('servicio.forzar_tipo') && ! $usuario->hasRole('admin')) {
                        throw new RuntimeException('No tiene permisos para forzar el tipo de orden de servicio.');
                    }
                    if (empty(trim($datos['motivo_forzado'] ?? ''))) {
                        throw new InvalidArgumentException('Debe especificar el motivo por el cual fuerza el tipo de orden.');
                    }
                    $tipo = $tipoDeseado;
                }
            }

            // Si es tipo garantía y la serie está vendida, pasa a 'en_garantia'
            if ($tipo === TipoOrdenServicio::GARANTIA && $serie !== null) {
                if ($serie->estado === EstadoSerie::VENDIDA) {
                    $serie->update([
                        'estado' => EstadoSerie::EN_GARANTIA,
                        'observaciones' => trim(($serie->observaciones ?? '')."\nIngreso a servicio técnico por garantía"),
                    ]);
                }
            }

            // Generar código único correlativo OS-000001
            $ultimoId = (int) OrdenServicio::withTrashed()->max('id');
            $nuevoCodigo = 'OS-'.str_pad((string) ($ultimoId + 1), 6, '0', STR_PAD_LEFT);

            $fechaPromesa = ! empty($datos['fecha_promesa']) ? Carbon::parse($datos['fecha_promesa'])->toDateString() : null;

            $orden = OrdenServicio::create([
                'codigo' => $nuevoCodigo,
                'tipo' => $tipo,
                'serie_id' => $serie?->id,
                'producto_id' => $producto?->id,
                'equipo_descripcion' => $datos['equipo_descripcion'] ?? null,
                'serie_externa' => $datos['serie_externa'] ?? null,
                'cliente_id' => $cliente->id,
                'garantia_id' => $garantia?->id,
                'falla_reportada' => trim($datos['falla_reportada']),
                'estado_recepcion' => trim($datos['estado_recepcion']),
                'estado' => EstadoOrdenServicio::RECIBIDO,
                'recibido_por' => $usuario->id,
                'recibido_en' => Carbon::now(),
                'fecha_promesa' => $fechaPromesa,
                'garantia_reparacion_dias' => (int) ($datos['garantia_reparacion_dias'] ?? 30),
            ]);

            // Registrar evento inicial de la línea de tiempo
            OrdenServicioEvento::create([
                'orden_servicio_id' => $orden->id,
                'estado_anterior' => null,
                'estado_nuevo' => EstadoOrdenServicio::RECIBIDO,
                'comentario' => ! empty($datos['motivo_forzado'])
                    ? 'Equipo recibido. Tipo forzado: '.trim($datos['motivo_forzado'])
                    : 'Equipo recibido en taller.',
                'user_id' => $usuario->id,
            ]);

            // Registrar fotos si se proporcionaron
            if (! empty($datos['fotos']) && is_array($datos['fotos'])) {
                foreach ($datos['fotos'] as $fotoData) {
                    if (! empty($fotoData['ruta'])) {
                        OrdenServicioFoto::create([
                            'orden_servicio_id' => $orden->id,
                            'ruta' => $fotoData['ruta'],
                            'descripcion' => $fotoData['descripcion'] ?? null,
                            'momento' => $fotoData['momento'] ?? 'ingreso',
                        ]);
                    }
                }
            }

            return $orden->fresh(['cliente', 'serie', 'producto', 'garantia', 'eventos', 'receptor']);
        });
    }

    /**
     * Cambiar estado de una orden de servicio validando la máquina de estados.
     */
    public function cambiarEstado(
        OrdenServicio $orden,
        EstadoOrdenServicio $nuevoEstado,
        ?string $comentario,
        User $usuario
    ): OrdenServicio {
        return DB::transaction(function () use ($orden, $nuevoEstado, $comentario, $usuario) {
            $ordenBloqueada = OrdenServicio::where('id', $orden->id)->lockForUpdate()->firstOrFail();
            $estadoAnterior = $ordenBloqueada->estado;

            if (! $estadoAnterior->puedePasarA($nuevoEstado)) {
                throw new InvalidArgumentException(
                    "Transición no permitida: no se puede pasar una orden de '{$estadoAnterior->etiqueta()}' a '{$nuevoEstado->etiqueta()}'."
                );
            }

            $ordenBloqueada->update([
                'estado' => $nuevoEstado,
            ]);

            OrdenServicioEvento::create([
                'orden_servicio_id' => $ordenBloqueada->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $nuevoEstado,
                'comentario' => $comentario,
                'user_id' => $usuario->id,
            ]);

            return $ordenBloqueada->fresh(['eventos']);
        });
    }

    /**
     * Asignar técnico responsable a la orden de servicio.
     */
    public function asignarTecnico(OrdenServicio $orden, User $tecnico, User $asignador): OrdenServicio
    {
        return DB::transaction(function () use ($orden, $tecnico, $asignador) {
            $ordenBloqueada = OrdenServicio::where('id', $orden->id)->lockForUpdate()->firstOrFail();

            $ordenBloqueada->update([
                'tecnico_id' => $tecnico->id,
            ]);

            OrdenServicioEvento::create([
                'orden_servicio_id' => $ordenBloqueada->id,
                'estado_anterior' => $ordenBloqueada->estado,
                'estado_nuevo' => $ordenBloqueada->estado,
                'comentario' => "Técnico asignado: {$tecnico->name}",
                'user_id' => $asignador->id,
            ]);

            return $ordenBloqueada->fresh(['tecnico']);
        });
    }

    /**
     * Registrar diagnóstico técnico. Pasa automáticamente a DIAGNOSTICO si estaba en RECIBIDO.
     */
    public function registrarDiagnostico(OrdenServicio $orden, string $diagnostico, User $usuario): OrdenServicio
    {
        $diagnosticoLimpio = trim($diagnostico);
        if (empty($diagnosticoLimpio)) {
            throw new InvalidArgumentException('El diagnóstico técnico no puede estar vacío.');
        }

        return DB::transaction(function () use ($orden, $diagnosticoLimpio, $usuario) {
            $ordenBloqueada = OrdenServicio::where('id', $orden->id)->lockForUpdate()->firstOrFail();

            if ($ordenBloqueada->estado === EstadoOrdenServicio::RECIBIDO) {
                $this->cambiarEstado($ordenBloqueada, EstadoOrdenServicio::DIAGNOSTICO, 'Inicio de diagnóstico', $usuario);
            }

            $ordenBloqueada->update([
                'diagnostico' => $diagnosticoLimpio,
                'tecnico_id' => $ordenBloqueada->tecnico_id ?? $usuario->id,
            ]);

            OrdenServicioEvento::create([
                'orden_servicio_id' => $ordenBloqueada->id,
                'estado_anterior' => $ordenBloqueada->estado,
                'estado_nuevo' => $ordenBloqueada->estado,
                'comentario' => "Diagnóstico registrado: {$diagnosticoLimpio}",
                'user_id' => $usuario->id,
            ]);

            return $ordenBloqueada->fresh();
        });
    }

    /**
     * Cotizar orden de servicio. Para órdenes de tipo 'pago', pasa a esperando_aprobacion.
     */
    public function cotizar(OrdenServicio $orden, float|string $costoEstimado, User $usuario): OrdenServicio
    {
        $costo = round((float) $costoEstimado, 2);
        if ($costo < 0) {
            throw new InvalidArgumentException('El costo estimado no puede ser negativo.');
        }

        return DB::transaction(function () use ($orden, $costo, $usuario) {
            $ordenBloqueada = OrdenServicio::where('id', $orden->id)->lockForUpdate()->firstOrFail();

            $ordenBloqueada->update([
                'costo_estimado' => $costo,
                'costo_final' => $costo,
            ]);

            if ($ordenBloqueada->esPago()) {
                if ($ordenBloqueada->estado === EstadoOrdenServicio::RECIBIDO) {
                    $this->cambiarEstado($ordenBloqueada, EstadoOrdenServicio::DIAGNOSTICO, 'Diagnóstico para cotización', $usuario);
                }
                if ($ordenBloqueada->estado === EstadoOrdenServicio::DIAGNOSTICO) {
                    $this->cambiarEstado(
                        $ordenBloqueada,
                        EstadoOrdenServicio::ESPERANDO_APROBACION,
                        "Cotización enviada: S/ {$costo}. En espera de confirmación del cliente.",
                        $usuario
                    );
                }
            }

            return $ordenBloqueada->fresh();
        });
    }

    /**
     * Registrar respuesta del cliente sobre la cotización.
     */
    public function registrarAprobacionCliente(OrdenServicio $orden, bool $aprobado, User $usuario): OrdenServicio
    {
        return DB::transaction(function () use ($orden, $aprobado, $usuario) {
            $ordenBloqueada = OrdenServicio::where('id', $orden->id)->lockForUpdate()->firstOrFail();

            if ($ordenBloqueada->estado !== EstadoOrdenServicio::ESPERANDO_APROBACION) {
                throw new InvalidArgumentException('La orden no se encuentra en estado esperando_aprobacion.');
            }

            $ordenBloqueada->update([
                'aprobado_por_cliente' => $aprobado,
                'aprobado_en' => Carbon::now(),
            ]);

            if ($aprobado) {
                // Aprobado por el cliente -> pasa a en_reparacion
                $this->cambiarEstado(
                    $ordenBloqueada,
                    EstadoOrdenServicio::EN_REPARACION,
                    'Cotización aprobada por el cliente. Pasa a reparación.',
                    $usuario
                );
            } else {
                // Rechazado por el cliente -> pasa a cancelado
                $this->cambiarEstado(
                    $ordenBloqueada,
                    EstadoOrdenServicio::CANCELADO,
                    'Cotización rechazada por el cliente. Orden cancelada.',
                    $usuario
                );
            }

            return $ordenBloqueada->fresh();
        });
    }

    /**
     * Agregar repuesto a una orden de servicio.
     * Si es producto del inventario, descuenta stock y serie.
     * Si la orden es de tipo GARANTIA, el precio al cliente es 0 y el costo lo asume la tienda.
     *
     * @param array{
     *     producto_id?: int|null,
     *     descripcion: string,
     *     cantidad?: int,
     *     costo_unitario?: float|string|null,
     *     precio_unitario?: float|string|null,
     *     serie_id?: int|null
     * } $datosRepuesto
     */
    public function agregarRepuesto(OrdenServicio $orden, array $datosRepuesto, User $usuario): OrdenServicioRepuesto
    {
        $descripcion = trim($datosRepuesto['descripcion'] ?? '');
        if (empty($descripcion)) {
            throw new InvalidArgumentException('La descripción del repuesto es obligatoria.');
        }

        $cantidad = isset($datosRepuesto['cantidad']) ? (int) $datosRepuesto['cantidad'] : 1;
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad de repuestos debe ser mayor a 0.');
        }

        return DB::transaction(function () use ($orden, $datosRepuesto, $descripcion, $cantidad, $usuario) {
            $ordenBloqueada = OrdenServicio::where('id', $orden->id)->lockForUpdate()->firstOrFail();

            if (in_array($ordenBloqueada->estado, [EstadoOrdenServicio::ENTREGADO, EstadoOrdenServicio::CANCELADO], true)) {
                throw new InvalidArgumentException('No se pueden agregar repuestos a una orden cerrada o cancelada.');
            }

            $productoId = ! empty($datosRepuesto['producto_id']) ? (int) $datosRepuesto['producto_id'] : null;
            $serieId = ! empty($datosRepuesto['serie_id']) ? (int) $datosRepuesto['serie_id'] : null;
            $costoUnitario = 0.0;
            $precioUnitario = 0.0;

            if ($productoId !== null) {
                $producto = Producto::where('id', $productoId)->lockForUpdate()->firstOrFail();
                $costoUnitario = (float) $producto->precio_compra_referencial;
                $precioUnitario = (float) $producto->precio_venta;

                if ($producto->controla_series) {
                    if ($serieId === null) {
                        throw new InvalidArgumentException("El producto '{$producto->nombre}' requiere seleccionar una serie.");
                    }
                    if ($cantidad !== 1) {
                        throw new InvalidArgumentException('Para productos con número de serie la cantidad debe ser 1.');
                    }

                    $serieRepuesto = Serie::where('id', $serieId)->lockForUpdate()->firstOrFail();
                    if ($serieRepuesto->producto_id !== $producto->id) {
                        throw new InvalidArgumentException('La serie seleccionada no corresponde al producto.');
                    }
                    if ($serieRepuesto->estado !== EstadoSerie::DISPONIBLE) {
                        throw new InvalidArgumentException("La serie '{$serieRepuesto->numero_serie}' no está disponible (estado actual: {$serieRepuesto->estado->value}).");
                    }

                    // La serie del repuesto pasa a 'baja' o 'en_garantia' según uso en reparación
                    $serieRepuesto->update([
                        'estado' => EstadoSerie::BAJA,
                        'observaciones' => trim(($serieRepuesto->observaciones ?? '')."\nUtilizado como repuesto en orden {$ordenBloqueada->codigo}"),
                    ]);
                } else {
                    $disponible = $this->inventarioService->stockDisponible($producto);
                    if ($disponible < $cantidad) {
                        throw new InvalidArgumentException("Stock insuficiente para el repuesto '{$producto->nombre}' (disponible: {$disponible}, requerido: {$cantidad}).");
                    }
                }

                // Registrar movimiento de inventario de SALIDA
                $stockActual = $this->inventarioService->stockDisponible($producto);
                MovimientoInventario::create([
                    'producto_id' => $producto->id,
                    'serie_id' => $serieId,
                    'tipo' => TipoMovimiento::SALIDA,
                    'cantidad' => -$cantidad,
                    'stock_resultante' => $stockActual - $cantidad,
                    'referencia_tipo' => OrdenServicio::class,
                    'referencia_id' => $ordenBloqueada->id,
                    'motivo' => "Consumo como repuesto en Orden {$ordenBloqueada->codigo}",
                    'user_id' => $usuario->id,
                ]);
            }

            if (isset($datosRepuesto['costo_unitario'])) {
                $costoUnitario = (float) $datosRepuesto['costo_unitario'];
            }
            if (isset($datosRepuesto['precio_unitario'])) {
                $precioUnitario = (float) $datosRepuesto['precio_unitario'];
            }

            // Regla de Oro: Si la orden es de GARANTIA, el precio al cliente es 0 y el costo lo asume la tienda
            if ($ordenBloqueada->esGarantia()) {
                $precioUnitario = 0.0;
            }

            $repuesto = OrdenServicioRepuesto::create([
                'orden_servicio_id' => $ordenBloqueada->id,
                'producto_id' => $productoId,
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'costo_unitario' => $costoUnitario,
                'precio_unitario' => $precioUnitario,
                'serie_id' => $serieId,
            ]);

            // Si es de pago y tiene precio, actualizamos el costo final acumulado de la orden
            if ($ordenBloqueada->esPago() && $precioUnitario > 0) {
                $nuevoCostoFinal = (float) ($ordenBloqueada->costo_final ?? 0) + ($precioUnitario * $cantidad);
                $ordenBloqueada->update(['costo_final' => $nuevoCostoFinal]);
            }

            return $repuesto;
        });
    }

    /**
     * Marcar una orden como reparada. Encola notificación al cliente.
     */
    public function marcarReparado(
        OrdenServicio $orden,
        string $solucion,
        User $usuario,
        ?float $costoFinal = null
    ): OrdenServicio {
        $solucionLimpia = trim($solucion);
        if (empty($solucionLimpia)) {
            throw new InvalidArgumentException('Debe detallar la solución técnica implementada.');
        }

        return DB::transaction(function () use ($orden, $solucionLimpia, $usuario, $costoFinal) {
            $ordenBloqueada = OrdenServicio::where('id', $orden->id)->lockForUpdate()->firstOrFail();

            if (! in_array($ordenBloqueada->estado, [EstadoOrdenServicio::EN_REPARACION, EstadoOrdenServicio::DIAGNOSTICO], true)) {
                // Si estaba en esperando_repuesto y se repara directamente, pasamos a reparado
                if ($ordenBloqueada->estado !== EstadoOrdenServicio::ESPERANDO_REPUESTO) {
                    throw new InvalidArgumentException("No se puede marcar como reparada una orden en estado {$ordenBloqueada->estado->value}.");
                }
            }

            $datosUpdate = [
                'solucion' => $solucionLimpia,
            ];

            if ($costoFinal !== null) {
                $datosUpdate['costo_final'] = $costoFinal;
            } elseif ($ordenBloqueada->esGarantia()) {
                $datosUpdate['costo_final'] = 0.00;
            }

            $ordenBloqueada->update($datosUpdate);

            $this->cambiarEstado(
                $ordenBloqueada,
                EstadoOrdenServicio::REPARADO,
                "Reparación finalizada: {$solucionLimpia}",
                $usuario
            );

            // Encolar notificación por correo al cliente si tiene email registrado
            if (! empty($ordenBloqueada->cliente->email)) {
                NotificarOrdenReparadaEmailJob::dispatch($ordenBloqueada->id);
            }

            // Notificación WhatsApp si el servicio está configurado
            if ($this->whatsappNotifier !== null) {
                $this->whatsappNotifier->notificar($ordenBloqueada, "Su orden {$ordenBloqueada->codigo} está lista para entrega.");
            }

            return $ordenBloqueada->fresh(['eventos', 'cliente']);
        });
    }

    /**
     * Cobrar una orden de servicio de tipo 'pago' creando una venta en VentaService.
     *
     * @param array{
     *     tipo_comprobante?: string|TipoComprobanteVenta,
     *     pagos: array<int, array{metodo: string|MetodoPago, monto: float|int|string, referencia?: string|null}>
     * } $datosCobro
     */
    public function cobrar(OrdenServicio $orden, array $datosCobro, User $usuario): Venta
    {
        if (! $usuario->can('servicio.cobrar') && ! $usuario->hasRole('admin')) {
            throw new RuntimeException('No tiene permisos para cobrar servicios técnicos.');
        }

        return DB::transaction(function () use ($orden, $datosCobro, $usuario) {
            $ordenBloqueada = OrdenServicio::where('id', $orden->id)->lockForUpdate()->firstOrFail();

            if ($ordenBloqueada->esGarantia()) {
                throw new InvalidArgumentException('Las órdenes de servicio por garantía no tienen costo para el cliente.');
            }

            if ($ordenBloqueada->venta_id !== null) {
                throw new InvalidArgumentException("La orden de servicio {$ordenBloqueada->codigo} ya fue cobrada previamente (Venta #{$ordenBloqueada->venta_id}).");
            }

            $montoCobro = (float) ($ordenBloqueada->costo_final ?? $ordenBloqueada->costo_estimado ?? 0);
            if ($montoCobro <= 0) {
                throw new InvalidArgumentException('El monto a cobrar debe ser mayor a 0.');
            }

            // Exigir caja abierta del usuario
            $caja = CajaSesion::where('user_id', $usuario->id)
                ->where('estado', EstadoCaja::ABIERTA->value)
                ->first();

            if (! $caja) {
                throw new RuntimeException("El usuario {$usuario->name} no tiene una sesión de caja abierta para registrar el cobro.");
            }

            // Construir o resolver producto de servicio "Servicio Técnico"
            $catId = $ordenBloqueada->producto?->categoria_id ?? Categoria::first()?->id ?? 1;
            $marcaId = $ordenBloqueada->producto?->marca_id ?? Marca::first()?->id ?? 1;

            $productoServicio = Producto::firstOrCreate(
                ['sku' => 'SERV-TEC-001'],
                [
                    'nombre' => 'Servicio Técnico / Reparación',
                    'slug' => 'servicio-tecnico-reparacion',
                    'descripcion' => 'Mano de obra y servicios de diagnóstico y reparación',
                    'categoria_id' => $catId,
                    'marca_id' => $marcaId,
                    'precio_venta' => $montoCobro,
                    'costo' => 0.00,
                    'controla_series' => false,
                    'stock_minimo' => 0,
                    'activo' => true,
                ]
            );

            // Asegurar que el concepto de servicio tenga stock para la venta
            $stockActualServicio = $this->inventarioService->stockDisponible($productoServicio);
            if ($stockActualServicio < 1) {
                MovimientoInventario::create([
                    'producto_id' => $productoServicio->id,
                    'serie_id' => null,
                    'tipo' => TipoMovimiento::INGRESO,
                    'cantidad' => 1000,
                    'stock_resultante' => $stockActualServicio + 1000,
                    'motivo' => 'Stock virtual para servicio de mano de obra y reparaciones',
                    'user_id' => $usuario->id,
                ]);
            }

            $tipoComprobante = $datosCobro['tipo_comprobante'] ?? TipoComprobanteVenta::BOLETA;
            $itemsVenta = [
                [
                    'producto_id' => $productoServicio->id,
                    'cantidad' => 1,
                    'precio_unitario' => $montoCobro,
                    'descripcion' => "Servicio Técnico OS {$ordenBloqueada->codigo}: {$ordenBloqueada->falla_reportada}",
                ],
            ];

            $pagos = $datosCobro['pagos'] ?? [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => $montoCobro],
            ];

            $venta = $this->ventaService->registrar(
                datos: [
                    'cliente_id' => $ordenBloqueada->cliente_id,
                    'tipo_comprobante' => $tipoComprobante,
                    'observaciones' => "Cobro de Orden de Servicio {$ordenBloqueada->codigo}",
                ],
                items: $itemsVenta,
                pagos: $pagos,
                vendedor: $usuario
            );

            $ordenBloqueada->update([
                'venta_id' => $venta->id,
                'costo_final' => $montoCobro,
            ]);

            OrdenServicioEvento::create([
                'orden_servicio_id' => $ordenBloqueada->id,
                'estado_anterior' => $ordenBloqueada->estado,
                'estado_nuevo' => $ordenBloqueada->estado,
                'comentario' => "Cobro realizado exitosamente mediante Venta {$venta->codigo} por S/ {$montoCobro}.",
                'user_id' => $usuario->id,
            ]);

            return $venta;
        });
    }

    /**
     * Entregar equipo al cliente.
     */
    public function entregar(OrdenServicio $orden, string $entregadoA, User $usuario): OrdenServicio
    {
        if (! $usuario->can('servicio.entregar') && ! $usuario->hasRole('admin')) {
            throw new RuntimeException('No tiene permisos para entregar equipos.');
        }

        $entregadoALimpio = trim($entregadoA);
        if (empty($entregadoALimpio)) {
            throw new InvalidArgumentException('Debe especificar el nombre o DNI de la persona que recibe el equipo.');
        }

        return DB::transaction(function () use ($orden, $entregadoALimpio, $usuario) {
            $ordenBloqueada = OrdenServicio::where('id', $orden->id)->lockForUpdate()->firstOrFail();

            // Si es de tipo PAGO y tiene costo mayor a 0, DEBE haberse cobrado antes
            $montoACobrar = (float) ($ordenBloqueada->costo_final ?? 0);
            if ($ordenBloqueada->esPago() && $montoACobrar > 0 && $ordenBloqueada->venta_id === null) {
                throw new RuntimeException(
                    "No se puede entregar la orden {$ordenBloqueada->codigo} porque tiene un cobro pendiente de S/ {$montoACobrar}."
                );
            }

            // Exige estar en estado REPARADO, SIN_SOLUCION o CANCELADO
            if (! in_array($ordenBloqueada->estado, [EstadoOrdenServicio::REPARADO, EstadoOrdenServicio::SIN_SOLUCION, EstadoOrdenServicio::CANCELADO], true)) {
                throw new InvalidArgumentException(
                    "No se puede entregar un equipo en estado '{$ordenBloqueada->estado->etiqueta()}'. Debe estar reparado, sin solución o cancelado."
                );
            }

            // Si la orden fue por garantía y tenía una serie de la tienda, la serie vuelve a 'vendida'
            if ($ordenBloqueada->esGarantia() && $ordenBloqueada->serie_id !== null) {
                $serie = Serie::where('id', $ordenBloqueada->serie_id)->lockForUpdate()->first();
                if ($serie && $serie->estado === EstadoSerie::EN_GARANTIA) {
                    $serie->update([
                        'estado' => EstadoSerie::VENDIDA,
                        'observaciones' => trim(($serie->observaciones ?? '')."\nRetorno de servicio técnico y entrega al cliente"),
                    ]);
                }
            }

            $ordenBloqueada->update([
                'entregado_a' => $entregadoALimpio,
                'entregado_en' => Carbon::now(),
            ]);

            $this->cambiarEstado(
                $ordenBloqueada,
                EstadoOrdenServicio::ENTREGADO,
                "Equipo entregado a {$entregadoALimpio}. Garantía de servicio: {$ordenBloqueada->garantia_reparacion_dias} días.",
                $usuario
            );

            return $ordenBloqueada->fresh(['serie', 'eventos']);
        });
    }

    /**
     * Marcar una orden como sin solución y enlazar con Devolución / Cambio.
     */
    public function sinSolucion(OrdenServicio $orden, string $motivo, User $usuario): OrdenServicio
    {
        $motivoLimpio = trim($motivo);
        if (empty($motivoLimpio)) {
            throw new InvalidArgumentException('Debe detallar el motivo por el cual el equipo no tiene solución técnica.');
        }

        return DB::transaction(function () use ($orden, $motivoLimpio, $usuario) {
            $ordenBloqueada = OrdenServicio::where('id', $orden->id)->lockForUpdate()->firstOrFail();

            $ordenBloqueada->update([
                'solucion' => "Sin solución técnica: {$motivoLimpio}",
                'costo_final' => 0.00,
            ]);

            $this->cambiarEstado(
                $ordenBloqueada,
                EstadoOrdenServicio::SIN_SOLUCION,
                "Declarado sin solución técnica: {$motivoLimpio}",
                $usuario
            );

            return $ordenBloqueada->fresh(['eventos']);
        });
    }
}
