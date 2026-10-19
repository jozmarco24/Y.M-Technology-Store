<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoPagoOnline;
use App\Enums\EstadoPedido;
use App\Enums\EstadoSerie;
use App\Enums\MetodoPago;
use App\Enums\Pasarela;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDocumentoCliente;
use App\Jobs\EnviarPagoConfirmadoEmailJob;
use App\Models\Cliente;
use App\Models\PagoOnline;
use App\Models\Pedido;
use App\Models\PedidoEvento;
use App\Models\Serie;
use App\Models\User;
use App\Models\WebhookRecibido;
use App\Services\Pagos\CulqiPasarela;
use App\Services\Pagos\NiubizPasarela;
use App\Services\Pagos\PasarelaPago;
use App\Services\Pagos\PasarelaSimulada;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PagoOnlineService
{
    public function __construct(
        protected VentaService $ventaService,
        protected PedidoService $pedidoService,
        protected CuponService $cuponService
    ) {}

    /**
     * Resolver la instancia de la pasarela según configuración o parámetro.
     */
    public function resolverPasarela(Pasarela|string $pasarela): PasarelaPago
    {
        $pasarelaEnum = $pasarela instanceof Pasarela ? $pasarela : Pasarela::from($pasarela);

        return match ($pasarelaEnum) {
            Pasarela::CULQI => app(CulqiPasarela::class),
            Pasarela::NIUBIZ => app(NiubizPasarela::class),
            default => app(PasarelaSimulada::class),
        };
    }

    /**
     * Iniciar un pago online generando el registro con idempotency_key único.
     *
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     */
    public function iniciar(Pedido $pedido, Pasarela|string $pasarela, array $opciones = []): array
    {
        $pasarelaEnum = $pasarela instanceof Pasarela ? $pasarela : Pasarela::from($pasarela);

        if ($pedido->estado !== EstadoPedido::PENDIENTE_PAGO) {
            throw new InvalidArgumentException("El pedido {$pedido->codigo} no está pendiente de pago (estado: {$pedido->estado->etiqueta()}).");
        }

        if ($pedido->estaExpirado()) {
            throw new RuntimeException("La reserva del pedido {$pedido->codigo} ha expirado. Por favor, genere un nuevo pedido.");
        }

        $idempotencyKey = (string) Str::uuid();

        $pagoOnline = PagoOnline::create([
            'pedido_id' => $pedido->id,
            'pasarela' => $pasarelaEnum,
            'estado' => EstadoPagoOnline::PENDIENTE,
            'monto' => $pedido->total,
            'moneda' => 'PEN',
            'idempotency_key' => $idempotencyKey,
            'intentos' => 1,
        ]);

        if (in_array($pasarelaEnum, [Pasarela::YAPE_MANUAL, Pasarela::TRANSFERENCIA], true)) {
            return [
                'pago_id' => $pagoOnline->id,
                'idempotency_key' => $idempotencyKey,
                'pasarela' => $pasarelaEnum->value,
                'monto' => $pagoOnline->monto,
                'estado' => $pagoOnline->estado->value,
                'mensaje' => 'Instrucciones para pago manual enviadas.',
            ];
        }

        $driver = $this->resolverPasarela($pasarelaEnum);
        $resultadoDriver = $driver->crearCargo($pedido, $pagoOnline, $opciones);

        return array_merge([
            'pago_id' => $pagoOnline->id,
            'idempotency_key' => $idempotencyKey,
            'pasarela' => $pasarelaEnum->value,
            'monto' => $pagoOnline->monto,
        ], $resultadoDriver);
    }

    /**
     * Confirmar un pago online convirtiendo el pedido en Venta real del sistema (o reembolso si fue tardío).
     *
     * @param  array<string, mixed>  $datosPasarela
     */
    public function confirmar(PagoOnline $pagoOnline, array $datosPasarela = []): Pedido
    {
        return DB::transaction(function () use ($pagoOnline, $datosPasarela) {
            // Lock de fila en pago y pedido
            /** @var PagoOnline $pago */
            $pago = PagoOnline::where('id', $pagoOnline->id)->lockForUpdate()->firstOrFail();
            /** @var Pedido $pedido */
            $pedido = Pedido::where('id', $pago->pedido_id)->lockForUpdate()->firstOrFail();

            if ($pago->estado === EstadoPagoOnline::PAGADO) {
                return $pedido; // Ya procesado previamente (idempotente)
            }

            // 1. Verificar si la reserva expiró antes del pago
            $reservaVencida = $pedido->estaExpirado() || $pedido->estado === EstadoPedido::EXPIRADO;

            if ($reservaVencida) {
                // Intentar re-reservar las series originales
                $reReservaExitosa = $this->intentarReReservarSeries($pedido);

                if (! $reReservaExitosa) {
                    // No se pudo re-reservar: marcar pago como pagado pero pedido para REEMBOLSO automático
                    $pago->update([
                        'estado' => EstadoPagoOnline::PAGADO,
                        'referencia_externa' => $datosPasarela['referencia'] ?? $pago->referencia_externa,
                        'respuesta_json' => array_merge((array) $pago->respuesta_json, $datosPasarela),
                        'pagado_en' => Carbon::now(),
                    ]);

                    $pedido->update([
                        'estado' => EstadoPedido::REEMBOLSADO,
                        'cancelado_motivo' => 'Pago recibido tras expiración de reserva. El stock ya no está disponible, marcado para reembolso.',
                    ]);

                    PedidoEvento::create([
                        'pedido_id' => $pedido->id,
                        'estado_anterior' => $pedido->estado->value,
                        'estado_nuevo' => EstadoPedido::REEMBOLSADO->value,
                        'comentario' => 'Pago tardío: stock original no disponible. Solicitado reembolso automático al cliente.',
                        'user_id' => null,
                    ]);

                    return $pedido;
                }
            }

            // 2. Marcar PagoOnline como pagado
            $pago->update([
                'estado' => EstadoPagoOnline::PAGADO,
                'referencia_externa' => $datosPasarela['referencia'] ?? $pago->referencia_externa,
                'respuesta_json' => array_merge((array) $pago->respuesta_json, $datosPasarela),
                'pagado_en' => Carbon::now(),
            ]);

            // 3. Crear o asociar Cliente para facturación electrónica
            $cliente = $this->obtenerOCrearClienteParaPedido($pedido);

            // 4. Preparar ítems para VentaService
            $pedido->loadMissing(['items.itemSeries.serie']);
            $itemsVenta = [];

            foreach ($pedido->items as $item) {
                $seriesIds = $item->itemSeries->pluck('serie_id')->filter()->all();

                if (! empty($seriesIds)) {
                    foreach ($seriesIds as $serieId) {
                        // Pasar serie de 'reservada' a 'disponible' momentáneamente para que VentaService la tome de forma estándar
                        Serie::where('id', $serieId)->update(['estado' => EstadoSerie::DISPONIBLE]);

                        $itemsVenta[] = [
                            'producto_id' => $item->producto_id,
                            'serie_id' => $serieId,
                            'cantidad' => 1,
                            'precio_unitario' => $item->precio_unitario,
                            'descuento' => 0.00,
                            'descripcion' => $item->descripcion,
                        ];
                    }
                } else {
                    $itemsVenta[] = [
                        'producto_id' => $item->producto_id,
                        'cantidad' => $item->cantidad,
                        'precio_unitario' => $item->precio_unitario,
                        'descuento' => 0.00,
                        'descripcion' => $item->descripcion,
                    ];
                }
            }

            // 5. Usuario virtual para ventas web (admin o primer usuario activo)
            $usuarioSistema = User::role('admin')->first() ?? User::firstOrFail();

            // Mapear pasarela a método de pago de Venta
            $metodoPagoVenta = match ($pago->pasarela) {
                Pasarela::YAPE_MANUAL => MetodoPago::YAPE,
                Pasarela::TRANSFERENCIA => MetodoPago::TRANSFERENCIA,
                default => MetodoPago::TARJETA,
            };

            $tipoComprobanteEnum = match ($pedido->tipo_comprobante) {
                'factura' => TipoComprobanteVenta::FACTURA,
                default => TipoComprobanteVenta::BOLETA,
            };

            // 6. Convertir pedido en venta real con VentaService (usando caja virtual WEB y canal 'web')
            $venta = $this->ventaService->registrar(
                datos: [
                    'cliente_id' => $cliente->id,
                    'tipo_comprobante' => $tipoComprobanteEnum,
                    'observaciones' => "Generado desde Pedido Web {$pedido->codigo}",
                    'descuento_global' => (float) $pedido->descuento,
                    'canal' => 'web',
                ],
                items: $itemsVenta,
                pagos: [
                    [
                        'metodo' => $metodoPagoVenta,
                        'monto' => $pedido->total,
                        'referencia' => $pago->referencia_externa ?? $pago->idempotency_key,
                    ],
                ],
                vendedor: $usuarioSistema
            );

            // 7. Incrementar uso de cupón si fue aplicado
            if ($pedido->cupon_id && $pedido->cupon) {
                $this->cuponService->registrarUso($pedido->cupon);
            }

            // 8. Actualizar pedido a PAGADO enlazado con la venta
            $estadoAnterior = $pedido->estado->value;
            $pedido->update([
                'estado' => EstadoPedido::PAGADO,
                'venta_id' => $venta->id,
                'cliente_id' => $cliente->id,
                'reserva_expira_en' => null,
            ]);

            PedidoEvento::create([
                'pedido_id' => $pedido->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => EstadoPedido::PAGADO->value,
                'comentario' => "Pago confirmado vía {$pago->pasarela->etiqueta()}. Convertido a Venta {$venta->codigo}.",
                'user_id' => null,
            ]);

            EnviarPagoConfirmadoEmailJob::dispatch($pedido->id);

            return $pedido;
        });
    }

    /**
     * Procesar un webhook recibido evitando duplicados y validando la firma.
     *
     * @param  array<string, mixed>  $payload
     */
    public function procesarWebhook(string $pasarela, array $payload, ?string $firma = null): array
    {
        $pasarelaEnum = Pasarela::from($pasarela);
        $driver = $this->resolverPasarela($pasarelaEnum);

        // 1. Validar firma del webhook
        if (! $driver->validarWebhook($payload, $firma)) {
            Log::warning("Firma de webhook inválida para {$pasarela}", ['payload' => $payload]);
            throw new InvalidArgumentException("La firma de autenticación del webhook de {$pasarela} es inválida.");
        }

        // 2. Extraer evento_id según pasarela
        $eventoId = (string) ($payload['id'] ?? $payload['data']['id'] ?? $payload['transaction_id'] ?? Str::uuid()->toString());

        // 3. Verificar si el webhook ya fue recibido previamente (idempotencia)
        $webhookExistente = WebhookRecibido::where('pasarela', $pasarela)
            ->where('evento_id', $eventoId)
            ->first();

        if ($webhookExistente && $webhookExistente->procesado) {
            return [
                'procesado' => true,
                'duplicado' => true,
                'mensaje' => 'Webhook ya procesado previamente.',
            ];
        }

        $registroWebhook = $webhookExistente ?? WebhookRecibido::create([
            'pasarela' => $pasarela,
            'evento_id' => $eventoId,
            'payload' => $payload,
            'procesado' => false,
        ]);

        try {
            // Identificar pedido o pago_online mediante metadata o referencia
            $idempotencyKey = $payload['data']['metadata']['idempotency_key'] ?? $payload['metadata']['idempotency_key'] ?? null;
            $pago = null;

            if ($idempotencyKey) {
                $pago = PagoOnline::where('idempotency_key', $idempotencyKey)->first();
            }

            if (! $pago && isset($payload['data']['id'])) {
                $pago = PagoOnline::where('referencia_externa', $payload['data']['id'])->first();
            }

            if ($pago) {
                $this->confirmar($pago, [
                    'referencia' => $eventoId,
                    'webhook_payload' => $payload,
                ]);
            }

            $registroWebhook->update([
                'procesado' => true,
                'procesado_en' => Carbon::now(),
            ]);

            return [
                'procesado' => true,
                'duplicado' => false,
                'mensaje' => 'Webhook procesado y pago confirmado con éxito.',
            ];
        } catch (\Throwable $e) {
            $registroWebhook->update([
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Procesar un reembolso de un pago online.
     */
    public function reembolsar(PagoOnline $pagoOnline, float $monto, string $motivo, User $usuario): array
    {
        return DB::transaction(function () use ($pagoOnline, $monto, $motivo, $usuario) {
            $pago = PagoOnline::where('id', $pagoOnline->id)->lockForUpdate()->firstOrFail();

            if ($pago->estado !== EstadoPagoOnline::PAGADO) {
                throw new InvalidArgumentException("Solo se pueden reembolsar pagos en estado pagado (estado actual: {$pago->estado->etiqueta()}).");
            }

            if ($monto <= 0 || $monto > (float) $pago->monto) {
                throw new InvalidArgumentException("El monto a reembolsar debe ser mayor a 0 y no superar S/ {$pago->monto}.");
            }

            $driver = $this->resolverPasarela($pago->pasarela);
            $resultado = $driver->reembolsar($pago, $monto, $motivo);

            if (! empty($resultado['exito'])) {
                $pago->update([
                    'estado' => EstadoPagoOnline::REEMBOLSADO,
                    'respuesta_json' => array_merge((array) $pago->respuesta_json, [
                        'reembolso' => $resultado,
                        'reembolsado_por' => $usuario->id,
                        'reembolsado_en' => Carbon::now()->toIso8601String(),
                    ]),
                ]);

                $pago->pedido->update([
                    'estado' => EstadoPedido::REEMBOLSADO,
                    'cancelado_motivo' => "Reembolso procesado: {$motivo}",
                ]);

                PedidoEvento::create([
                    'pedido_id' => $pago->pedido_id,
                    'estado_anterior' => EstadoPedido::PAGADO->value,
                    'estado_nuevo' => EstadoPedido::REEMBOLSADO->value,
                    'comentario' => "Reembolso de S/ {$monto} autorizado por {$usuario->name}: {$motivo}",
                    'user_id' => $usuario->id,
                ]);
            }

            return $resultado;
        });
    }

    /**
     * Consultar el estado de un pago online ante la pasarela correspondiente.
     *
     * @return array<string, mixed>
     */
    public function consultar(PagoOnline $pagoOnline): array
    {
        $pasarela = $this->resolverPasarela($pagoOnline->pasarela);

        return $pasarela->consultar($pagoOnline);
    }

    /**
     * Intenta re-reservar las mismas series del pedido si aún siguen disponibles.
     */
    protected function intentarReReservarSeries(Pedido $pedido): bool
    {
        $pedido->loadMissing(['items.itemSeries.serie']);

        foreach ($pedido->items as $item) {
            foreach ($item->itemSeries as $itemSerie) {
                $serie = Serie::where('id', $itemSerie->serie_id)->lockForUpdate()->first();
                if (! $serie || $serie->estado !== EstadoSerie::DISPONIBLE) {
                    return false;
                }
            }
        }

        // Si todas siguen disponibles, reservarlas nuevamente
        foreach ($pedido->items as $item) {
            foreach ($item->itemSeries as $itemSerie) {
                $serie = Serie::find($itemSerie->serie_id);
                $serie?->update(['estado' => EstadoSerie::RESERVADA]);
            }
        }

        return true;
    }

    /**
     * Encontrar o crear cliente físico para emitir comprobante SUNAT.
     */
    protected function obtenerOCrearClienteParaPedido(Pedido $pedido): Cliente
    {
        $doc = trim($pedido->documento_numero);
        if ($doc !== '') {
            $clienteExistente = Cliente::where('numero_documento', $doc)->first();
            if ($clienteExistente) {
                return $clienteExistente;
            }
        }

        $tipoDocEnum = match ($pedido->documento_tipo) {
            'RUC' => TipoDocumentoCliente::RUC,
            'CE' => TipoDocumentoCliente::CE,
            'DNI' => TipoDocumentoCliente::DNI,
            default => TipoDocumentoCliente::SIN_DOC,
        };

        $nombre = ! empty($pedido->razon_social) ? $pedido->razon_social : ($pedido->clienteWeb?->nombre ?? 'Cliente Web');

        return Cliente::create([
            'tipo_documento' => $tipoDocEnum,
            'numero_documento' => $doc ?: '00000000',
            'nombre_razon_social' => $nombre,
            'direccion' => $pedido->direccion_fiscal ?? ($pedido->direccion_snapshot['direccion'] ?? 'Lima, Perú'),
            'email' => $pedido->email_contacto,
            'telefono' => $pedido->telefono_contacto,
            'activo' => true,
        ]);
    }
}
