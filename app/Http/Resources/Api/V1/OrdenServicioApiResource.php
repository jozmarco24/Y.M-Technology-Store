<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\OrdenServicio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrdenServicio
 */
class OrdenServicioApiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $esAdmin = $request->user()?->hasRole('admin') ?? false;

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'tipo' => $this->tipo->value,
            'estado' => $this->estado->value,
            'falla_reportada' => $this->falla_reportada,
            'estado_recepcion' => $this->estado_recepcion,
            'diagnostico' => $this->diagnostico,
            'solucion' => $this->solucion,
            'fecha_promesa' => $this->fecha_promesa?->format('Y-m-d'),
            'recibido_en' => $this->recibido_en->toIso8601String(),
            'entregado_en' => $this->entregado_en?->toIso8601String(),
            'entregado_a' => $this->entregado_a,
            'garantia_reparacion_dias' => $this->garantia_reparacion_dias,

            // Información del equipo y serie
            'equipo' => [
                'descripcion' => $this->producto?->nombre ?? $this->equipo_descripcion,
                'serie' => $this->serie?->numero_serie ?? $this->serie_externa,
            ],

            // Cliente
            'cliente' => [
                'id' => $this->cliente_id,
                'nombre' => $this->cliente->nombre_razon_social,
                'documento' => $this->cliente->numero_documento,
            ],

            // Técnico asignado
            'tecnico' => $this->tecnico ? [
                'id' => $this->tecnico->id,
                'nombre' => $this->tecnico->name,
            ] : null,

            // Costos solo visibles si el usuario es administrador
            $this->mergeWhen($esAdmin, [
                'costo_estimado' => $this->costo_estimado ? (float) $this->costo_estimado : null,
                'costo_final' => $this->costo_final ? (float) $this->costo_final : null,
                'venta_id' => $this->venta_id,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
