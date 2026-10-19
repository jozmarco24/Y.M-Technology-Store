<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoSerie;
use App\Models\Producto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CatalogoWebService
{
    /**
     * Listar productos publicados para el catálogo web con filtros y stock disponible.
     *
     * @param array{
     *     categoria_id?: int|null,
     *     marca_id?: int|null,
     *     precio_min?: float|int|string|null,
     *     precio_max?: float|int|string|null,
     *     buscar?: string|null,
     *     orden?: string|null,
     *     solo_con_stock?: bool|null
     * } $filtros
     */
    public function listarPublicados(array $filtros = [], int $porPagina = 12): LengthAwarePaginator
    {
        $query = Producto::query()
            ->publicados()
            ->with([
                'categoria:id,nombre,slug',
                'marca:id,nombre,slug',
                'imagenes',
            ])
            ->withCount(['series as stock_disponible' => function (Builder $q) {
                $q->where('estado', EstadoSerie::DISPONIBLE);
            }]);

        // Filtro por categoría
        if (! empty($filtros['categoria_id'])) {
            $query->where('categoria_id', (int) $filtros['categoria_id']);
        }

        // Filtro por marca
        if (! empty($filtros['marca_id'])) {
            $query->where('marca_id', (int) $filtros['marca_id']);
        }

        // Filtro por rango de precio
        if (isset($filtros['precio_min']) && is_numeric($filtros['precio_min'])) {
            $query->where('precio_venta', '>=', (float) $filtros['precio_min']);
        }

        if (isset($filtros['precio_max']) && is_numeric($filtros['precio_max'])) {
            $query->where('precio_venta', '<=', (float) $filtros['precio_max']);
        }

        // Búsqueda por texto (nombre, SKU, modelo, descripción)
        if (! empty($filtros['buscar']) && trim((string) $filtros['buscar']) !== '') {
            $termino = trim((string) $filtros['buscar']);
            $query->where(function (Builder $q) use ($termino) {
                $q->where('nombre', 'ilike', "%{$termino}%")
                    ->orWhere('sku', 'ilike', "%{$termino}%")
                    ->orWhere('modelo', 'ilike', "%{$termino}%")
                    ->orWhere('descripcion', 'ilike', "%{$termino}%");
            });
        }

        // Filtro opcional solo productos con stock disponible
        if (! empty($filtros['solo_con_stock'])) {
            $query->conStock();
        }

        // Ordenamiento
        $orden = $filtros['orden'] ?? 'recientes';
        match ($orden) {
            'precio_asc' => $query->orderBy('precio_venta', 'asc'),
            'precio_desc' => $query->orderBy('precio_venta', 'desc'),
            'nombre_asc' => $query->orderBy('nombre', 'asc'),
            'nombre_desc' => $query->orderBy('nombre', 'desc'),
            default => $query->orderBy('destacado', 'desc')->orderBy('id', 'desc'),
        };

        return $query->paginate($porPagina);
    }

    /**
     * Obtener detalle de un producto por slug para la tienda web.
     * Nunca expone costos ni precios de compra.
     */
    public function obtenerPorSlug(string $slug): ?Producto
    {
        return Producto::query()
            ->publicados()
            ->where('slug', $slug)
            ->with([
                'categoria:id,nombre,slug',
                'marca:id,nombre,slug',
                'imagenes',
            ])
            ->withCount(['series as stock_disponible' => function (Builder $q) {
                $q->where('estado', EstadoSerie::DISPONIBLE);
            }])
            ->first();
    }

    /**
     * Obtener productos relacionados (misma categoría o marca).
     *
     * @return Collection<int, Producto>
     */
    public function obtenerRelacionados(Producto $producto, int $limite = 4): Collection
    {
        return Producto::query()
            ->publicados()
            ->where('id', '!=', $producto->id)
            ->where(function (Builder $q) use ($producto) {
                $q->where('categoria_id', $producto->categoria_id)
                    ->orWhere('marca_id', $producto->marca_id);
            })
            ->with([
                'categoria:id,nombre,slug',
                'marca:id,nombre,slug',
                'imagenes',
            ])
            ->withCount(['series as stock_disponible' => function (Builder $q) {
                $q->where('estado', EstadoSerie::DISPONIBLE);
            }])
            ->limit($limite)
            ->get();
    }
}
