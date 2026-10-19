<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PagoOnlineService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookCulqiController extends Controller
{
    /**
     * Procesar webhook de Culqi con validación de firma e idempotencia.
     */
    public function handle(Request $request, PagoOnlineService $pagoOnlineService): JsonResponse
    {
        $firma = $request->header('x-culqi-signature') ?? $request->header('HTTP_X_CULQI_SIGNATURE');
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        if (empty($payload)) {
            return response()->json(['error' => 'Payload vacío'], 400);
        }

        try {
            $pagoOnlineService->procesarWebhook('culqi', $payload, $firma);

            return response()->json([
                'success' => true,
                'mensaje' => 'Webhook procesado satisfactoriamente',
            ], 200);
        } catch (Exception $e) {
            Log::warning("Fallo al procesar webhook de Culqi: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
