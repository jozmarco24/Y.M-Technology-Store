<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Producto;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Producto
 */
class ProductoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user('sanctum') ?? $request->user();
        $puedeVerCostos = $user?->can('costos.ver') ?? false;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'modelo' => $this->modelo,
            'categoria' => [
                'id' => $this->categoria?->id,
                'nombre' => $this->categoria?->nombre,
                'slug' => $this->categoria?->slug,
            ],
            'marca' => [
                'id' => $this->marca?->id,
                'nombre' => $this->marca?->nombre,
                'slug' => $this->marca?->slug,
            ],
            $this->mergeWhen($puedeVerCostos, [
                'precio_compra_referencial' => (float) $this->precio_compra_referencial,
            ]),
            'precio_venta' => (float) $this->precio_venta,
            'incluye_igv' => $this->incluye_igv,
            'controla_series' => $this->controla_series,
            'stock_minimo' => $this->stock_minimo,
            'stock_disponible' => app(InventarioService::class)->stockDisponible($this->resource),
            'imagen' => $this->imagen,
            'especificaciones' => $this->especificaciones,
            'activo' => $this->activo,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
