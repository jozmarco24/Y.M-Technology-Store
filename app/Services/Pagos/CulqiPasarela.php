<?php

declare(strict_types=1);

namespace App\Services\Pagos;

use App\Models\PagoOnline;
use App\Models\Pedido;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CulqiPasarela implements PasarelaPago
{
    protected string $secretKey;

    protected string $publicKey;

    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = (string) config('services.culqi.secret_key', env('CULQI_SECRET_KEY', 'sk_test_mock_culqi'));
        $this->publicKey = (string) config('services.culqi.public_key', env('CULQI_PUBLIC_KEY', 'pk_test_mock_culqi'));
        $this->webhookSecret = config('services.culqi.webhook_secret', env('CULQI_WEBHOOK_SECRET'));
    }

    /**
     * Crear u obtener lo necesario para el frontend (público o backend charge si vino token).
     *
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     */
    public function crearCargo(Pedido $pedido, PagoOnline $pagoOnline, array $opciones = []): array
    {
        $token = $opciones['token'] ?? null;

        // Si se recibe un token de tarjeta desde el frontend, ejecuta el cargo en Culqi
        if ($token) {
            $montoEnCentimos = (int) round(((float) $pagoOnline->monto) * 100);

            try {
                $response = Http::withToken($this->secretKey)
                    ->timeout(15)
                    ->post('https://api.culqi.com/v2/charges', [
                        'amount' => $montoEnCentimos,
                        'currency_code' => $pagoOnline->moneda ?? 'PEN',
                        'email' => $pedido->email_contacto,
                        'source_id' => $token,
                        'description' => "Pedido {$pedido->codigo} - SYS-COPM",
                        'antifraud_details' => [
                            'first_name' => $pedido->direccion_snapshot['destinatario'] ?? 'Cliente',
                            'phone' => $pedido->telefono_contacto ?? '999999999',
                        ],
                    ]);

                $data = $response->json() ?? [];

                if ($response->successful() && ($data['outcome']['type'] ?? '') === 'venta_exitosa') {
                    return [
                        'pagado' => true,
                        'referencia' => $data['id'] ?? null,
                        'respuesta' => $data,
                        'public_key' => $this->publicKey,
                    ];
                }

                $mensajeError = $data['user_message'] ?? ($data['merchant_message'] ?? 'La transacción fue denegada.');

                return [
                    'pagado' => false,
                    'error' => $mensajeError,
                    'respuesta' => $data,
                    'public_key' => $this->publicKey,
                ];
            } catch (Exception $e) {
                Log::error("Error de conexión con API Culqi: {$e->getMessage()}");

                return [
                    'pagado' => false,
                    'error' => 'No se pudo comunicar con la pasarela de pagos. Por favor, reintenta.',
                    'respuesta' => [],
                    'public_key' => $this->publicKey,
                ];
            }
        }

        // Datos devueltos al frontend para abrir el modal o checkout Culqi
        return [
            'public_key' => $this->publicKey,
            'pedido_codigo' => $pedido->codigo,
            'monto' => $pagoOnline->monto,
            'moneda' => $pagoOnline->moneda,
            'idempotency_key' => $pagoOnline->idempotency_key,
        ];
    }

    /**
     * Consultar estado de un cargo en la API de Culqi antes de reintentar para no cobrar 2 veces.
     *
     * @return array<string, mixed>
     */
    public function consultar(PagoOnline $pagoOnline): array
    {
        if (empty($pagoOnline->referencia_externa)) {
            return [
                'pagado' => false,
                'estado' => 'pendiente',
                'referencia' => null,
                'monto' => (float) $pagoOnline->monto,
                'respuesta' => [],
            ];
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(10)
                ->get("https://api.culqi.com/v2/charges/{$pagoOnline->referencia_externa}");

            $data = $response->json() ?? [];
            $pagado = ($data['outcome']['type'] ?? '') === 'venta_exitosa';

            return [
                'pagado' => $pagado,
                'estado' => $pagado ? 'pagado' : 'fallido',
                'referencia' => $pagoOnline->referencia_externa,
                'monto' => (float) ($data['amount'] ?? 0) / 100.0,
                'respuesta' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Error al consultar cargo Culqi {$pagoOnline->referencia_externa}: {$e->getMessage()}");

            return [
                'pagado' => false,
                'estado' => 'error_consulta',
                'referencia' => $pagoOnline->referencia_externa,
                'monto' => (float) $pagoOnline->monto,
                'respuesta' => [],
            ];
        }
    }

    /**
     * Reembolsar un cargo mediante API de Culqi.
     *
     * @return array<string, mixed>
     */
    public function reembolsar(PagoOnline $pagoOnline, float $monto, string $motivo): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(15)
                ->post('https://api.culqi.com/v2/refunds', [
                    'charge_id' => $pagoOnline->referencia_externa,
                    'amount' => (int) round($monto * 100),
                    'reason' => $motivo,
                ]);

            $data = $response->json() ?? [];
            $exito = $response->successful();

            return [
                'exito' => $exito,
                'referencia_reembolso' => $data['id'] ?? null,
                'respuesta' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Error al emitir reembolso en Culqi: {$e->getMessage()}");

            return [
                'exito' => false,
                'referencia_reembolso' => null,
                'respuesta' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Validar firma del webhook proveniente de Culqi.
     *
     * @param  array<string, mixed>  $payload
     */
    public function validarWebhook(array $payload, ?string $firma = null): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        if (empty($firma)) {
            return false;
        }

        // Validación HMAC SHA256 para webhooks de Culqi
        $calculada = hash_hmac('sha256', (string) json_encode($payload), $this->webhookSecret);

        return hash_equals($calculada, $firma);
    }
}
