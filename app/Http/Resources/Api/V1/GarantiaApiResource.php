<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Garantia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Garantia
 */
class GarantiaApiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serie' => $this->serie?->numero_serie,
            'producto' => [
                'id' => $this->serie?->producto_id,
                'nombre' => $this->serie?->producto?->nombre,
                'sku' => $this->serie?->producto?->sku,
            ],
            'cliente' => [
                'id' => $this->cliente_id,
                'nombre' => $this->cliente?->nombre_razon_social,
                'documento' => $this->cliente?->numero_documento,
            ],
            'fecha_inicio' => $this->fecha_inicio->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin->format('Y-m-d'),
            'dias_restantes' => $this->diasRestantes(),
            'estado' => $this->estado->value,
            'es_vigente' => $this->esVigente(),
            'dias_cambio_hasta' => $this->dias_cambio_hasta->format('Y-m-d'),
            'en_plazo_cambio' => $this->estaEnPlazoCambio(),
            'condiciones' => $this->condiciones_snapshot,
        ];
    }
}
