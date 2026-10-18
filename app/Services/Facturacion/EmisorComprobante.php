<?php

declare(strict_types=1);

namespace App\Services\Facturacion;

use App\Models\ComprobanteElectronico;
use App\Models\Venta;

interface EmisorComprobante
{
    /**
     * Enviar comprobante electrónico a SUNAT / PSE.
     */
    public function emitir(Venta $venta): ResultadoEmision;

    /**
     * Consultar el estado de un comprobante emitido previamente.
     */
    public function consultar(ComprobanteElectronico $comprobante): ResultadoEmision;

    /**
     * Anular (comunicación de baja o resumen diario de anulación) un comprobante aceptado.
     */
    public function anular(ComprobanteElectronico $comprobante, string $motivo): ResultadoEmision;
}
