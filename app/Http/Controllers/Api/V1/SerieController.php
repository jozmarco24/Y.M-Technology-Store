<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SerieResource;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SerieController extends Controller
{
    public function show(string $numero, Request $request, InventarioService $service): JsonResponse|SerieResource
    {
        $serie = $service->buscarSerie($numero);

        if (! $serie) {
            return response()->json([
                'mensaje' => 'La serie solicitada no fue encontrada.',
            ], 404);
        }

        $serie->load(['producto', 'compraDetalle.compra']);

        return new SerieResource($serie);
    }
}
