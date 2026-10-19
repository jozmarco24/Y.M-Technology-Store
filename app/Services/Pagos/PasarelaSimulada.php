<?php

declare(strict_types=1);

namespace App\Services\Pagos;

use App\Models\PagoOnline;
use App\Models\Pedido;
use Illuminate\Support\Str;

class PasarelaSimulada implements PasarelaPago
{
    public function crearCargo(Pedido $pedido, PagoOnline $pagoOnline, array $opciones = []): array
    {
        $referencia = 'SIM-'.strtoupper(Str::random(10));
        $pagoOnline->update([
            'referencia_externa' => $referencia,
            'respuesta_json' => [
                'simulado' => true,
                'checkout_url' => url("/pagos/simulado/{$pagoOnline->idempotency_key}"),
                'monto' => $pagoOnline->monto,
                'moneda' => $pagoOnline->moneda,
            ],
        ]);

        return [
            'checkout_url' => url("/pagos/simulado/{$pagoOnline->idempotency_key}"),
            'referencia' => $referencia,
            'idempotency_key' => $pagoOnline->idempotency_key,
        ];
    }

    public function consultar(PagoOnline $pagoOnline): array
    {
        return [
            'pagado' => true,
            'estado' => 'pagado',
            'referencia' => $pagoOnline->referencia_externa,
            'monto' => (float) $pagoOnline->monto,
            'respuesta' => ['status' => 'paid', 'simulado' => true],
        ];
    }

    public function reembolsar(PagoOnline $pagoOnline, float $monto, string $motivo): array
    {
        return [
            'exito' => true,
            'referencia_reembolso' => 'REF-SIM-'.strtoupper(Str::random(8)),
            'respuesta' => ['reembolsado' => true, 'monto' => $monto, 'motivo' => $motivo],
        ];
    }

    public function validarWebhook(array $payload, ?string $firma = null): bool
    {
        if ($firma === 'firma_invalida') {
            return false;
        }

        return true;
    }
}
