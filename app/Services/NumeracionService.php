<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoComprobanteVenta;
use App\Models\SerieComprobante;
use InvalidArgumentException;

class NumeracionService
{
    /**
     * Obtiene el siguiente correlativo para un tipo de comprobante de manera atómica con lockForUpdate.
     * Incrementa inmediatamente el correlativo_actual para reservar el número.
     *
     * @return array{serie: string, numero: int}
     */
    public function obtenerSiguienteCorrelativo(TipoComprobanteVenta $tipo, ?string $serie = null): array
    {
        $tipoStr = $tipo->value;

        $serieModel = SerieComprobante::where('tipo_comprobante', $tipoStr)
            ->where('activo', true)
            ->when($serie !== null, fn ($query) => $query->where('serie', $serie))
            ->orderBy('serie')
            ->lockForUpdate()
            ->first();

        if (! $serieModel) {
            $detalle = $serie ? "serie {$serie}" : "tipo {$tipo->etiqueta()}";
            throw new InvalidArgumentException("No se encontró una serie activa para comprobantes de {$detalle}.");
        }

        $siguienteNumero = $serieModel->correlativo_actual + 1;

        $serieModel->update([
            'correlativo_actual' => $siguienteNumero,
        ]);

        return [
            'serie' => $serieModel->serie,
            'numero' => $siguienteNumero,
        ];
    }
}
