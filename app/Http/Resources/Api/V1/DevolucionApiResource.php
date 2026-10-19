<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Devolucion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Devolucion
 */
class DevolucionApiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'venta_id' => $this->venta_id,
            'cliente_id' => $this->cliente_id,
            'tipo' => $this->tipo->value,
            'motivo' => $this->motivo->value,
            'detalle_motivo' => $this->detalle_motivo,
            'estado' => $this->estado->value,
            'total_devuelto' => (float) $this->total_devuelto,
            'metodo_reembolso' => $this->metodo_reembolso?->value,
            'solicitado_por' => $this->solicitado_por,
            'aprobado_por' => $this->aprobado_por,
            'aprobado_en' => $this->aprobado_en?->toIso8601String(),
            'completada_en' => $this->completada_en?->toIso8601String(),
            'detalles' => $this->detalles->map(fn ($det) => [
                'id' => $det->id,
                'producto_id' => $det->producto_id,
                'serie_id' => $det->serie_id,
                'cantidad' => $det->cantidad,
                'monto_devuelto' => (float) $det->monto_devuelto,
                'estado_fisico' => $det->estado_fisico->value,
                'destino_serie' => $det->destino_serie,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
