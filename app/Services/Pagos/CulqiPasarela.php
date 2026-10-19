<?php

declare(strict_types=1);

namespace App\Services\Pagos;

use App\Models\PagoOnline;
use App\Models\Pedido;
use Illuminate\Support\Facades\Http;

class CulqiPasarela implements PasarelaPago
{
    protected string $secretKey;

    protected string $publicKey;

    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = (string) config('services.culqi.secret_key', env('CULQI_SECRET_KEY', ''));
        $this->publicKey = (string) config('services.culqi.public_key', env('CULQI_PUBLIC_KEY', ''));
        $this->webhookSecret = config('services.culqi.webhook_secret', env('CULQI_WEBHOOK_SECRET'));
    }

    public function crearCargo(Pedido $pedido, PagoOnline $pagoOnline, array $opciones = []): array
    {
        // En Culqi, el cargo suele crearse con un token de tarjeta generado en frontend o mediante Orden Culqi
        return [
            'public_key' => $this->publicKey,
            'pedido_codigo' => $pedido->codigo,
            'monto' => $pagoOnline->monto,
            'moneda' => $pagoOnline->moneda,
            'idempotency_key' => $pagoOnline->idempotency_key,
        ];
    }

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

        $response = Http::withToken($this->secretKey)
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
    }

    public function reembolsar(PagoOnline $pagoOnline, float $monto, string $motivo): array
    {
        $response = Http::withToken($this->secretKey)
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
    }

    public function validarWebhook(array $payload, ?string $firma = null): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        if (empty($firma)) {
            return false;
        }

        // Validación HMAC SHA256 para webhooks de Culqi
        $calculada = hash_hmac('sha256', json_encode($payload), $this->webhookSecret);

        return hash_equals($calculada, $firma);
    }
}
