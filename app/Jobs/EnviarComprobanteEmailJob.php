<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class EnviarComprobanteEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $ventaId
    ) {
        $this->onQueue(config('facturacion.cola', 'facturacion'));
    }

    public function handle(): void
    {
        $venta = Venta::with(['cliente', 'comprobanteElectronico'])->find($this->ventaId);

        if (! $venta || ! $venta->cliente?->email || ! $venta->comprobanteElectronico) {
            return;
        }

        $comprobante = $venta->comprobanteElectronico;

        if (! $comprobante->estaAceptado()) {
            return;
        }

        $email = $venta->cliente->email;
        $pdfUrl = $comprobante->enlace_pdf;
        $xmlUrl = $comprobante->enlace_xml;

        try {
            Mail::raw(
                "Estimado cliente,\n\nAdjuntamos la información de su comprobante electrónico {$comprobante->serie}-{$comprobante->numero}.\n".
                ($pdfUrl ? "PDF: {$pdfUrl}\n" : '').
                ($xmlUrl ? "XML: {$xmlUrl}\n" : '').
                "\nGracias por su compra.",
                function ($message) use ($email, $comprobante) {
                    $message->to($email)
                        ->subject("Comprobante Electrónico {$comprobante->serie}-{$comprobante->numero}");
                }
            );
        } catch (Throwable $e) {
            Log::warning("No se pudo enviar el correo del comprobante para venta {$this->ventaId}: {$e->getMessage()}");
        }
    }
}
