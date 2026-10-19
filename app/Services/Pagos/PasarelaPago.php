<?php

declare(strict_types=1);

namespace App\Services\Pagos;

use App\Models\PagoOnline;
use App\Models\Pedido;

interface PasarelaPago
{
    /**
     * Iniciar o crear una intención de cobro en la pasarela.
     *
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     */
    public function crearCargo(Pedido $pedido, PagoOnline $pagoOnline, array $opciones = []): array;

    /**
     * Consultar el estado del cargo en la pasarela de pagos.
     *
     * @return array{
     *     pagado: bool,
     *     estado: string,
     *     referencia: string|null,
     *     monto: float,
     *     respuesta: array<string, mixed>
     * }
     */
    public function consultar(PagoOnline $pagoOnline): array;

    /**
     * Procesar un reembolso total o parcial ante la pasarela.
     *
     * @return array{
     *     exito: bool,
     *     referencia_reembolso: string|null,
     *     respuesta: array<string, mixed>
     * }
     */
    public function reembolsar(PagoOnline $pagoOnline, float $monto, string $motivo): array;

    /**
     * Validar la firma o autenticidad de un webhook recibido.
     *
     * @param  array<string, mixed>  $payload
     */
    public function validarWebhook(array $payload, ?string $firma = null): bool;
}
