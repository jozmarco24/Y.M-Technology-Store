<?php

declare(strict_types=1);

namespace App\Services\Pagos;

use App\Models\PagoOnline;
use App\Models\Pedido;

class NiubizPasarela implements PasarelaPago
{
    public function crearCargo(Pedido $pedido, PagoOnline $pagoOnline, array $opciones = []): array
    {
        return [
            'pasarela' => 'niubiz',
            'monto' => $pagoOnline->monto,
            'pedido_codigo' => $pedido->codigo,
            'idempotency_key' => $pagoOnline->idempotency_key,
        ];
    }

    public function consultar(PagoOnline $pagoOnline): array
    {
        return [
            'pagado' => false,
            'estado' => 'pendiente',
            'referencia' => $pagoOnline->referencia_externa,
            'monto' => (float) $pagoOnline->monto,
            'respuesta' => ['mensaje' => 'Integración Niubiz'],
        ];
    }

    public function reembolsar(PagoOnline $pagoOnline, float $monto, string $motivo): array
    {
        return [
            'exito' => true,
            'referencia_reembolso' => 'REF-NIUBIZ-001',
            'respuesta' => ['monto' => $monto, 'motivo' => $motivo],
        ];
    }

    public function validarWebhook(array $payload, ?string $firma = null): bool
    {
        return true;
    }
}
