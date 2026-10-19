<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MetodoEntrega;
use App\Models\ZonaEnvio;

class EnvioService
{
    /**
     * Cotizar método de entrega según método y zona seleccionada.
     * Recojo en tienda siempre es gratuito (S/ 0.00).
     *
     * @return array{
     *     metodo: string,
     *     costo: float,
     *     dias_estimados: int,
     *     zona_id: int|null,
     *     nombre_zona: string
     * }
     */
    public function cotizar(MetodoEntrega|string $metodo, ?int $zonaEnvioId = null): array
    {
        $metodoEnum = $metodo instanceof MetodoEntrega ? $metodo : MetodoEntrega::from($metodo);

        if ($metodoEnum === MetodoEntrega::RECOJO_TIENDA) {
            return [
                'metodo' => $metodoEnum->value,
                'costo' => 0.00,
                'dias_estimados' => 0,
                'zona_id' => null,
                'nombre_zona' => 'Recojo en tienda principal',
            ];
        }

        if ($zonaEnvioId) {
            $zona = ZonaEnvio::where('id', $zonaEnvioId)->where('activo', true)->first();
            if ($zona) {
                return [
                    'metodo' => $metodoEnum->value,
                    'costo' => (float) $zona->costo,
                    'dias_estimados' => $zona->dias_estimados,
                    'zona_id' => $zona->id,
                    'nombre_zona' => $zona->nombre,
                ];
            }
        }

        // Costos base si no se especifica zona específica (solo DELIVERY_LIMA o ENVIO_PROVINCIA llegan aquí)
        $costoBase = $metodoEnum === MetodoEntrega::DELIVERY_LIMA ? 15.00 : 25.00;
        $diasBase = $metodoEnum === MetodoEntrega::DELIVERY_LIMA ? 1 : 3;

        return [
            'metodo' => $metodoEnum->value,
            'costo' => $costoBase,
            'dias_estimados' => $diasBase,
            'zona_id' => null,
            'nombre_zona' => $metodoEnum->etiqueta(),
        ];
    }
}
