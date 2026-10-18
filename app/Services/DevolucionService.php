<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoDevolucion;
use App\Enums\EstadoFisico;
use App\Enums\EstadoGarantia;
use App\Enums\EstadoSerie;
use App\Enums\MetodoPago;
use App\Enums\MotivoDevolucion;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDevolucion;
use App\Enums\TipoMovimiento;
use App\Enums\TipoMovimientoCaja;
use App\Models\Devolucion;
use App\Models\DevolucionDetalle;
use App\Models\Garantia;
use App\Models\MovimientoInventario;
use App\Models\NotaCredito;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\Facturacion\FacturacionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DevolucionService
{
    public function __construct(
        protected InventarioService $inventarioService,
        protected CajaService $cajaService,
        protected GarantiaService $garantiaService,
        protected CambioService $cambioService,
        protected NumeracionService $numeracionService,
        protected FacturacionService $facturacionService
    ) {}

    /**
     * Calcula el saldo restante devolvible (monto o cantidad) de un detalle de venta.
     */
    public function saldoDevolvible(VentaDetalle $detalle): array
    {
        $yaDevueltoCantidad = (int) DevolucionDetalle::where('venta_detalle_id', $detalle->id)
            ->whereHas('devolucion', fn ($q) => $q->whereIn('estado', [
                EstadoDevolucion::SOLICITADA,
                EstadoDevolucion::APROBADA,
                EstadoDevolucion::COMPLETADA,
            ]))
            ->sum('cantidad');

        $yaDevueltoMonto = (float) DevolucionDetalle::where('venta_detalle_id', $detalle->id)
            ->whereHas('devolucion', fn ($q) => $q->whereIn('estado', [
                EstadoDevolucion::SOLICITADA,
                EstadoDevolucion::APROBADA,
                EstadoDevolucion::COMPLETADA,
            ]))
            ->sum('monto_devuelto');

        $cantidadDisponible = max(0, $detalle->cantidad - $yaDevueltoCantidad);
        $montoDisponible = max(0.0, round((float) $detalle->subtotal - $yaDevueltoMonto, 2));

        return [
            'cantidad_disponible' => $cantidadDisponible,
            'monto_disponible' => $montoDisponible,
            'ya_devuelto_cantidad' => $yaDevueltoCantidad,
            'ya_devuelto_monto' => $yaDevueltoMonto,
        ];
    }

    /**
     * Solicitar una devolución o cambio.
     *
     * @param  array<int, array{
     *     venta_detalle_id: int,
     *     serie_id?: int|null,
     *     cantidad?: int,
     *     monto_devuelto?: float|int|string,
     *     estado_fisico: string|EstadoFisico,
     *     destino_serie?: string
     * }>  $items
     */
    public function solicitar(
        Venta $venta,
        array $items,
        string|TipoDevolucion $tipo,
        string|MotivoDevolucion $motivo,
        string $detalleMotivo,
        User $solicitante,
        ?string $metodoReembolso = null,
        bool $aprobacionExcepcionalAdmin = false
    ): Devolucion {
        if (! $venta->esCompletada()) {
            throw new InvalidArgumentException("Solo se pueden solicitar devoluciones sobre ventas completadas (estado actual: {$venta->estado->value}).");
        }

        if (empty($items)) {
            throw new InvalidArgumentException('Debe incluir al menos un ítem a devolver.');
        }

        $tipoEnum = $tipo instanceof TipoDevolucion ? $tipo : TipoDevolucion::from($tipo);
        $motivoEnum = $motivo instanceof MotivoDevolucion ? $motivo : MotivoDevolucion::from($motivo);
        $detalleMotivoLimpio = trim($detalleMotivo);

        if ($detalleMotivoLimpio === '') {
            throw new InvalidArgumentException('El detalle del motivo de devolución es obligatorio.');
        }

        $esAdmin = $solicitante->hasRole('admin');

        return DB::transaction(function () use (
            $venta,
            $items,
            $tipoEnum,
            $motivoEnum,
            $detalleMotivoLimpio,
            $solicitante,
            $metodoReembolso,
            $esAdmin,
            $aprobacionExcepcionalAdmin
        ) {
            $totalDevuelto = 0.0;
            $detallesParaCrear = [];
            $seriesEnEstaDevolucion = [];

            foreach ($items as $idx => $itemData) {
                $pos = $idx + 1;
                $ventaDetalleId = (int) ($itemData['venta_detalle_id'] ?? 0);
                $detalle = VentaDetalle::where('id', $ventaDetalleId)
                    ->where('venta_id', $venta->id)
                    ->first();

                if (! $detalle) {
                    throw new InvalidArgumentException("El ítem #{$pos} no pertenece a la venta {$venta->codigo}.");
                }

                $saldo = $this->saldoDevolvible($detalle);
                $cantidadSolicitada = isset($itemData['cantidad']) ? (int) $itemData['cantidad'] : 1;

                if ($cantidadSolicitada <= 0) {
                    throw new InvalidArgumentException("La cantidad a devolver en el ítem #{$pos} debe ser mayor a 0.");
                }

                $producto = $detalle->producto;
                $serieId = isset($itemData['serie_id']) && $itemData['serie_id'] ? (int) $itemData['serie_id'] : $detalle->serie_id;

                if ($producto->controla_series) {
                    if (! $serieId) {
                        throw new InvalidArgumentException("El producto '{$producto->nombre}' controla series y requiere especificar la serie a devolver.");
                    }

                    if (in_array($serieId, $seriesEnEstaDevolucion, true)) {
                        throw new InvalidArgumentException("La serie ID {$serieId} está duplicada en la solicitud de devolución.");
                    }
                    $seriesEnEstaDevolucion[] = $serieId;

                    // Validar si la serie ya fue devuelta en otra solicitud previa
                    $yaDevuelta = DevolucionDetalle::where('serie_id', $serieId)
                        ->whereHas('devolucion', fn ($q) => $q->whereIn('estado', [
                            EstadoDevolucion::SOLICITADA,
                            EstadoDevolucion::APROBADA,
                            EstadoDevolucion::COMPLETADA,
                        ]))
                        ->exists();

                    if ($yaDevuelta) {
                        throw new InvalidArgumentException("La serie ID {$serieId} ya tiene una devolución registrada o en proceso.");
                    }
                    // Validar plazo de garantía / cambio
                    $garantia = Garantia::where('serie_id', $serieId)->latest('id')->first();
                    $hoy = Carbon::today();

                    if ($garantia !== null) {
                        $enPlazoCambio = $garantia->estaEnPlazoCambio();
                        $garantiaVigente = $garantia->esVigente();

                        // Si no está en plazo de cambio:
                        // Solo se permite si el motivo es DEFECTO_FABRICA y la garantía está vigente,
                        // o si un admin lo autoriza expresamente.
                        if (! $enPlazoCambio) {
                            $permitePorDefecto = ($motivoEnum === MotivoDevolucion::DEFECTO_FABRICA && $garantiaVigente);
                            if (! $permitePorDefecto && ! ($esAdmin || $aprobacionExcepcionalAdmin)) {
                                throw new InvalidArgumentException("El plazo de cambio/devolución para la serie venció el {$garantia->dias_cambio_hasta->format('d/m/Y')}. Solo se aceptan cambios por defecto de fábrica en garantía.");
                            }
                        }
                    } else {
                        // Si no tiene garantía registrada (p. ej. venta antigua), verificar días de la venta
                        $diasDesdeVenta = (int) $hoy->diffInDays($venta->created_at);
                        if ($diasDesdeVenta > 7 && ! ($esAdmin || $aprobacionExcepcionalAdmin)) {
                            throw new InvalidArgumentException("El plazo regular de cambio (7 días) ha expirado (venta realizada hace {$diasDesdeVenta} días).");
                        }
                    }
                }

                if ($cantidadSolicitada > $saldo['cantidad_disponible']) {
                    throw new InvalidArgumentException("La cantidad solicitada ({$cantidadSolicitada}) supera la cantidad disponible para devolver ({$saldo['cantidad_disponible']}) en el ítem #{$pos}.");
                }

                // Cálculo proporcional o monto devuelto
                $precioUnitarioNeto = (float) $detalle->precio_unitario - (float) $detalle->descuento;
                $montoItem = isset($itemData['monto_devuelto'])
                    ? round((float) $itemData['monto_devuelto'], 2)
                    : round($precioUnitarioNeto * $cantidadSolicitada, 2);

                if ($montoItem <= 0) {
                    throw new InvalidArgumentException("El monto a devolver debe ser mayor a 0 en el ítem #{$pos}.");
                }

                if ($montoItem > $saldo['monto_disponible'] + 0.01) {
                    throw new InvalidArgumentException("El monto a devolver (S/ {$montoItem}) supera el saldo disponible (S/ {$saldo['monto_disponible']}) en el ítem #{$pos}.");
                }

                $estadoFisico = $itemData['estado_fisico'] instanceof EstadoFisico
                    ? $itemData['estado_fisico']
                    : EstadoFisico::from((string) $itemData['estado_fisico']);

                // Destino por defecto según estado físico si no se especifica
                $destinoSerie = $itemData['destino_serie'] ?? match ($estadoFisico) {
                    EstadoFisico::NUEVO_SELLADO, EstadoFisico::ABIERTO_OK => 'disponible',
                    EstadoFisico::CON_DEFECTO => 'en_garantia',
                    EstadoFisico::DANADO => 'baja',
                };

                $totalDevuelto += $montoItem;

                $detallesParaCrear[] = [
                    'venta_detalle_id' => $detalle->id,
                    'serie_id' => $serieId,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidadSolicitada,
                    'monto_devuelto' => $montoItem,
                    'estado_fisico' => $estadoFisico,
                    'destino_serie' => $destinoSerie,
                ];
            }

            // Generar código correlativo DEV-000001
            $ultimoId = (int) Devolucion::withTrashed()->max('id');
            $nuevoCodigo = 'DEV-'.str_pad((string) ($ultimoId + 1), 6, '0', STR_PAD_LEFT);

            $devolucion = Devolucion::create([
                'codigo' => $nuevoCodigo,
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'tipo' => $tipoEnum,
                'motivo' => $motivoEnum,
                'detalle_motivo' => $detalleMotivoLimpio,
                'estado' => EstadoDevolucion::SOLICITADA,
                'total_devuelto' => $totalDevuelto,
                'metodo_reembolso' => $metodoReembolso ? MetodoPago::from($metodoReembolso) : null,
                'solicitado_por' => $solicitante->id,
            ]);

            foreach ($detallesParaCrear as $det) {
                DevolucionDetalle::create(array_merge($det, ['devolucion_id' => $devolucion->id]));
            }

            return $devolucion->fresh(['detalles', 'venta', 'cliente', 'solicitante']);
        });
    }

    /**
     * Aprobar una devolución (Solo administradores).
     */
    public function aprobar(Devolucion $devolucion, User $usuario): Devolucion
    {
        if (! $usuario->hasRole('admin') && ! $usuario->hasPermissionTo('devoluciones.aprobar')) {
            throw new RuntimeException('Solo un usuario administrador puede aprobar devoluciones.');
        }

        if (! $devolucion->esSolicitada()) {
            throw new InvalidArgumentException("Solo se pueden aprobar devoluciones en estado solicitada (estado actual: {$devolucion->estado->value}).");
        }

        $devolucion->update([
            'estado' => EstadoDevolucion::APROBADA,
            'aprobado_por' => $usuario->id,
            'aprobado_en' => now(),
        ]);

        return $devolucion->fresh();
    }

    /**
     * Rechazar una devolución con motivo obligatorio.
     */
    public function rechazar(Devolucion $devolucion, string $motivoRechazo, User $usuario): Devolucion
    {
        $motivoLimpio = trim($motivoRechazo);
        if ($motivoLimpio === '') {
            throw new InvalidArgumentException('El motivo de rechazo es obligatorio.');
        }

        if (! $usuario->hasRole('admin') && ! $usuario->hasPermissionTo('devoluciones.aprobar')) {
            throw new RuntimeException('Solo un usuario administrador puede rechazar devoluciones.');
        }

        if ($devolucion->esCompletada()) {
            throw new InvalidArgumentException('No se puede rechazar una devolución que ya fue completada.');
        }

        $devolucion->update([
            'estado' => EstadoDevolucion::RECHAZADA,
            'aprobado_por' => $usuario->id,
            'aprobado_en' => now(),
            'observaciones' => trim(($devolucion->observaciones ?? '')."\nRechazado por {$usuario->name}: {$motivoLimpio}"),
        ]);

        return $devolucion->fresh();
    }

    /**
     * Completar la devolución y procesar movimientos físicos, caja, garantías y SUNAT.
     */
    public function completar(Devolucion $devolucion, User $usuario, ?int $serieNuevaId = null): Devolucion
    {
        return DB::transaction(function () use ($devolucion, $usuario, $serieNuevaId) {
            $devBloqueada = Devolucion::where('id', $devolucion->id)->lockForUpdate()->firstOrFail();

            if (! in_array($devBloqueada->estado, [EstadoDevolucion::SOLICITADA, EstadoDevolucion::APROBADA], true)) {
                throw new InvalidArgumentException("Solo se pueden completar devoluciones solicitadas o aprobadas (estado actual: {$devBloqueada->estado->value}).");
            }

            // Si la devolución no está aprobada previamente, solo admin o con permiso devoluciones.aprobar/completar puede completarla
            if ($devBloqueada->estado === EstadoDevolucion::SOLICITADA && ! $usuario->hasRole('admin') && ! $usuario->hasPermissionTo('devoluciones.completar') && ! $usuario->hasPermissionTo('devoluciones.aprobar')) {
                throw new RuntimeException('No tiene permisos para aprobar o completar devoluciones no autorizadas.');
            }

            $devBloqueada->load(['detalles.producto', 'detalles.serie', 'venta.cliente']);
            $venta = $devBloqueada->venta;

            // 1. Procesar destinos de series y movimientos de inventario
            foreach ($devBloqueada->detalles as $detalle) {
                $producto = Producto::where('id', $detalle->producto_id)->lockForUpdate()->firstOrFail();
                $destino = $detalle->destino_serie; // 'disponible', 'en_garantia', 'baja'

                if ($detalle->serie_id !== null && $detalle->serie !== null) {
                    $serie = Serie::where('id', $detalle->serie_id)->lockForUpdate()->firstOrFail();

                    $nuevoEstadoSerie = match ($destino) {
                        'disponible' => EstadoSerie::DISPONIBLE,
                        'en_garantia' => EstadoSerie::EN_GARANTIA,
                        'baja' => EstadoSerie::BAJA,
                        default => EstadoSerie::DISPONIBLE,
                    };

                    $serie->update([
                        'estado' => $nuevoEstadoSerie,
                        'observaciones' => trim(($serie->observaciones ?? '')."\nDevolución {$devBloqueada->codigo} (destino: {$nuevoEstadoSerie->value})"),
                    ]);

                    $tipoMov = match ($nuevoEstadoSerie) {
                        EstadoSerie::DISPONIBLE => TipoMovimiento::INGRESO,
                        EstadoSerie::EN_GARANTIA => TipoMovimiento::AJUSTE,
                        EstadoSerie::BAJA => TipoMovimiento::SALIDA,
                    };

                    $stockActual = $this->inventarioService->stockDisponible($producto);
                    MovimientoInventario::create([
                        'producto_id' => $producto->id,
                        'serie_id' => $serie->id,
                        'tipo' => $tipoMov,
                        'cantidad' => $nuevoEstadoSerie === EstadoSerie::DISPONIBLE ? 1 : 0,
                        'stock_resultante' => $stockActual,
                        'referencia_tipo' => Devolucion::class,
                        'referencia_id' => $devBloqueada->id,
                        'motivo' => "Retorno por devolución {$devBloqueada->codigo} ({$devBloqueada->motivo->etiqueta()})",
                        'user_id' => $usuario->id,
                    ]);

                    // La garantía de la serie devuelta pasa a ANULADA
                    Garantia::where('serie_id', $serie->id)
                        ->where('estado', EstadoGarantia::VIGENTE)
                        ->update(['estado' => EstadoGarantia::ANULADA]);
                } else {
                    // Producto sin serie: si el destino es disponible, reingresa al stock
                    if ($destino === 'disponible') {
                        $stockPrevio = $this->inventarioService->stockDisponible($producto);
                        $nuevoStock = $stockPrevio + $detalle->cantidad;

                        MovimientoInventario::create([
                            'producto_id' => $producto->id,
                            'serie_id' => null,
                            'tipo' => TipoMovimiento::INGRESO,
                            'cantidad' => $detalle->cantidad,
                            'stock_resultante' => $nuevoStock,
                            'referencia_tipo' => Devolucion::class,
                            'referencia_id' => $devBloqueada->id,
                            'motivo' => "Reingreso por devolución {$devBloqueada->codigo}",
                            'user_id' => $usuario->id,
                        ]);
                    }
                }
            }

            // 2. Procesar según tipo de devolución:
            // CASO A: Devolución de dinero -> Egreso en caja obligatorio
            if ($devBloqueada->tipo === TipoDevolucion::DEVOLUCION_DINERO) {
                $sesionActiva = $this->cajaService->obtenerSesionActiva($usuario);
                if (! $sesionActiva || ! $sesionActiva->estaAbierta()) {
                    throw new RuntimeException("Para completar una devolución de dinero se requiere que el usuario '{$usuario->name}' tenga una caja abierta.");
                }

                $metodo = $devBloqueada->metodo_reembolso ?? MetodoPago::EFECTIVO;

                $this->cajaService->registrarMovimiento(
                    cajaSesion: $sesionActiva,
                    tipo: TipoMovimientoCaja::EGRESO,
                    concepto: "Reembolso por devolución {$devBloqueada->codigo} (Venta {$venta->codigo})",
                    monto: (float) $devBloqueada->total_devuelto,
                    metodoPago: $metodo,
                    ventaId: $venta->id,
                    usuario: $usuario
                );
            }

            // CASO B: Nota de Crédito -> Generar y emitir electrónicamente si es boleta o factura
            if ($devBloqueada->tipo === TipoDevolucion::NOTA_CREDITO) {
                if ($venta->tipo_comprobante === TipoComprobanteVenta::NOTA_VENTA) {
                    throw new InvalidArgumentException('Las notas de venta no pueden generar notas de crédito electrónicas ante SUNAT.');
                }

                $serieNc = $venta->tipo_comprobante === TipoComprobanteVenta::FACTURA ? 'FC01' : 'BC01';
                $correlativo = $this->numeracionService->obtenerSiguienteCorrelativo('nota_credito', $serieNc);

                $notaCredito = NotaCredito::create([
                    'devolucion_id' => $devBloqueada->id,
                    'venta_id' => $venta->id,
                    'serie' => $correlativo['serie'],
                    'numero' => $correlativo['numero'],
                    'motivo_sunat' => '07', // Catálogo 09: 07 = Devolución por ítem
                    'monto' => $devBloqueada->total_devuelto,
                    'estado' => 'pendiente',
                ]);

                // Emitir de forma asíncrona o directa según emisor
                $this->facturacionService->emitirNotaCredito($notaCredito);
            }

            // CASO C: Cambio de Producto
            if ($devBloqueada->tipo === TipoDevolucion::CAMBIO_PRODUCTO) {
                if ($serieNuevaId !== null) {
                    $serieNueva = Serie::findOrFail($serieNuevaId);
                    $detalleConSerie = $devBloqueada->detalles()->whereNotNull('serie_id')->first();
                    if ($detalleConSerie && $detalleConSerie->serie) {
                        $this->cambioService->cambiarSerie(
                            devolucion: $devBloqueada,
                            serieOriginal: $detalleConSerie->serie,
                            serieNueva: $serieNueva,
                            usuario: $usuario
                        );
                    }
                }
            }

            // 3. Marcar devolución como completada
            $devBloqueada->update([
                'estado' => EstadoDevolucion::COMPLETADA,
                'completada_en' => now(),
            ]);

            return $devBloqueada->fresh(['detalles', 'notaCredito', 'cambiosSerie']);
        });
    }
}
