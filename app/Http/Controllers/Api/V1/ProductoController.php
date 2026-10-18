<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexProductoRequest;
use App\Http\Resources\Api\V1\ProductoResource;
use App\Models\Producto;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductoController extends Controller
{
    public function index(IndexProductoRequest $request): AnonymousResourceCollection
    {
        $query = Producto::query()
            ->with(['categoria', 'marca'])
            ->where('activo', true);

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', (int) $request->input('categoria_id'));
        }

        if ($request->filled('marca_id')) {
            $query->where('marca_id', (int) $request->input('marca_id'));
        }

        if ($request->filled('buscar')) {
            $buscar = (string) $request->input('buscar');
            $query->where(function ($q) use ($buscar) {
                $q->where('sku', 'ilike', "%{$buscar}%")
                    ->orWhere('nombre', 'ilike', "%{$buscar}%")
                    ->orWhere('modelo', 'ilike', "%{$buscar}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);

        return ProductoResource::collection($query->paginate($perPage));
    }

    public function show(int $id): ProductoResource
    {
        $producto = Producto::query()
            ->with(['categoria', 'marca'])
            ->findOrFail($id);

        return new ProductoResource($producto);
    }
}
