<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Serie;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Serie
 */
class SerieResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $puedeVerCostos = $user?->can('costos.ver') ?? false;

        $data = [
            'id' => $this->id,
            'numero_serie' => $this->numero_serie,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'fecha_ingreso' => $this->fecha_ingreso->toIso8601String(),
            'observaciones' => $this->observaciones,
            'producto' => [
                'id' => $this->producto?->id,
                'sku' => $this->producto?->sku,
                'nombre' => $this->producto?->nombre,
                'slug' => $this->producto?->slug,
                'precio_venta' => (float) ($this->producto?->precio_venta ?? 0),
            ],
            'compra_origen' => $this->compraDetalle?->compra ? [
                'id' => $this->compraDetalle->compra->id,
                'codigo' => $this->compraDetalle->compra->codigo,
                'fecha_emision' => $this->compraDetalle->compra->fecha_emision->format('Y-m-d'),
            ] : null,
        ];

        if ($puedeVerCostos) {
            $data['costo'] = (float) $this->costo;
        }

        return $data;
    }
}
