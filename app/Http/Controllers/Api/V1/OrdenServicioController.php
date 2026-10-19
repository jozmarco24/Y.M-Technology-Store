<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrdenServicioApiResource;
use App\Models\OrdenServicio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrdenServicioController extends Controller
{
    /**
     * Listado de órdenes de servicio con filtros y paginación.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $usuario = $request->user();

        $query = OrdenServicio::query()
            ->with(['cliente', 'producto', 'serie', 'tecnico'])
            ->latest('id');

        // Si el usuario es técnico y no es admin, solo ve las suyas
        if ($usuario && $usuario->hasRole('tecnico') && ! $usuario->hasRole('admin')) {
            $query->where('tecnico_id', $usuario->id);
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        // Filtro por tipo
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        // Filtro por cliente
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->input('cliente_id'));
        }

        // Filtro por técnico
        if ($request->filled('tecnico_id')) {
            $query->where('tecnico_id', $request->input('tecnico_id'));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return OrdenServicioApiResource::collection($query->paginate($perPage));
    }

    /**
     * Detalle de una orden de servicio por código (OS-000001).
     */
    public function show(Request $request, string $codigo): JsonResponse
    {
        $usuario = $request->user();
        $codLimpio = strtoupper(trim($codigo));

        $query = OrdenServicio::where('codigo', $codLimpio)
            ->with(['cliente', 'producto', 'serie', 'tecnico', 'repuestos', 'eventos.usuario']);

        if ($usuario && $usuario->hasRole('tecnico') && ! $usuario->hasRole('admin')) {
            $query->where('tecnico_id', $usuario->id);
        }

        $orden = $query->first();

        if (! $orden) {
            return response()->json([
                'message' => "No se encontró la orden de servicio '{$codLimpio}'.",
            ], 404);
        }

        return response()->json([
            'data' => new OrdenServicioApiResource($orden),
        ]);
    }
}
