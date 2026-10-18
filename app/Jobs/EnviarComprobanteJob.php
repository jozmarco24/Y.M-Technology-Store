<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Venta;
use App\Services\Facturacion\FacturacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EnviarComprobanteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Número máximo de intentos.
     */
    public int $tries = 5;

    /**
     * Backoff exponencial: 1 min, 5 min, 15 min, 1 h, 3 h.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900, 3600, 10800];

    public function __construct(
        public readonly int $ventaId
    ) {
        $this->onQueue(config('facturacion.cola', 'facturacion'));
    }

    public function handle(FacturacionService $facturacionService): void
    {
        $venta = Venta::with(['cliente', 'comprobanteElectronico'])->find($this->ventaId);

        if (! $venta) {
            Log::warning("EnviarComprobanteJob: No se encontró la venta ID {$this->ventaId}");

            return;
        }

        $comprobante = $facturacionService->emitir($venta);

        // Si el comprobante fue aceptado y el cliente tiene correo, despachar envío de PDF
        if ($comprobante->estaAceptado() && ! empty($venta->cliente?->email)) {
            EnviarComprobanteEmailJob::dispatch($venta->id)
                ->onQueue(config('facturacion.cola', 'facturacion'));
        }
    }

    /**
     * Manejo ante falla definitiva del job.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("EnviarComprobanteJob falló definitivamente para venta ID {$this->ventaId}: ".($exception?->getMessage() ?? 'Error desconocido'));
    }
}
