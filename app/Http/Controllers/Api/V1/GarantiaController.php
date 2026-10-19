<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GarantiaApiResource;
use App\Models\Garantia;
use App\Models\Serie;
use Illuminate\Http\JsonResponse;

class GarantiaController extends Controller
{
    /**
     * Consultar garantía por número de serie.
     */
    public function show(string $numeroSerie): JsonResponse
    {
        $sn = strtoupper(trim($numeroSerie));
        $serie = Serie::where('numero_serie', $sn)->first();

        if (! $serie) {
            return response()->json([
                'message' => "No se encontró la serie '{$sn}'.",
            ], 404);
        }

        $garantia = Garantia::where('serie_id', $serie->id)
            ->with(['serie.producto', 'cliente'])
            ->latest('id')
            ->first();

        if (! $garantia) {
            return response()->json([
                'message' => "La serie '{$sn}' no cuenta con registro de garantía.",
                'serie' => $serie->numero_serie,
                'producto' => $serie->producto?->nombre,
            ], 404);
        }

        return response()->json([
            'data' => new GarantiaApiResource($garantia),
        ]);
    }
}
