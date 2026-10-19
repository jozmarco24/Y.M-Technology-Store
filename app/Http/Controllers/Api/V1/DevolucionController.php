<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MotivoDevolucion;
use App\Enums\TipoDevolucion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SolicitarDevolucionApiRequest;
use App\Http\Resources\Api\V1\DevolucionApiResource;
use App\Models\Venta;
use App\Services\DevolucionService;
use Illuminate\Http\JsonResponse;

class DevolucionController extends Controller
{
    public function __construct(
        protected DevolucionService $devolucionService
    ) {}

    /**
     * Solicitar una devolución o cambio a través de la API.
     */
    public function store(SolicitarDevolucionApiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $venta = Venta::findOrFail($validated['venta_id']);

        $tipo = TipoDevolucion::from($validated['tipo']);
        $motivo = MotivoDevolucion::from($validated['motivo']);

        $devolucion = $this->devolucionService->solicitar(
            venta: $venta,
            items: $validated['items'],
            tipo: $tipo,
            motivo: $motivo,
            detalleMotivo: $validated['detalle_motivo'],
            solicitante: $request->user(),
            metodoReembolso: $validated['metodo_reembolso'] ?? null,
            aprobacionExcepcionalAdmin: (bool) ($validated['aprobacion_excepcional_admin'] ?? false)
        );

        return response()->json([
            'message' => 'Solicitud de devolución registrada exitosamente.',
            'data' => new DevolucionApiResource($devolucion),
        ], 201);
    }
}
