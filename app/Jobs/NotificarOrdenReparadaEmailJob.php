<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OrdenServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class NotificarOrdenReparadaEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $ordenServicioId
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $orden = OrdenServicio::with(['cliente'])->find($this->ordenServicioId);

        if (! $orden || ! $orden->cliente || ! $orden->cliente->email) {
            return;
        }

        $email = $orden->cliente->email;
        $clienteNombre = $orden->cliente->nombre_razon_social;
        $codigo = $orden->codigo;
        $solucion = $orden->solucion ?? 'Equipo reparado y verificado en laboratorio técnico.';
        $costoFinal = $orden->esGarantia() ? 'S/ 0.00 (Cubierto por Garantía)' : 'S/ '.number_format((float) ($orden->costo_final ?? 0), 2);

        try {
            Mail::raw(
                "Estimado(a) {$clienteNombre},\n\n".
                "Le informamos que su orden de servicio {$codigo} ha sido finalizada con éxito y su equipo se encuentra LISTO PARA ENTREGA en nuestra tienda.\n\n".
                "Detalle de la reparación:\n".
                "- Diagnóstico: {$orden->diagnostico}\n".
                "- Solución realizada: {$solucion}\n".
                "- Monto a pagar / liquidar: {$costoFinal}\n".
                "- Garantía de servicio: {$orden->garantia_reparacion_dias} días a partir de la entrega.\n\n".
                "Puede acercarse a nuestra tienda presentando su código {$codigo} o su documento de identidad.\n\n".
                "Atentamente,\nServicio Técnico SYS-COPM",
                function ($message) use ($email, $codigo) {
                    $message->to($email)
                        ->subject("Equipo Reparado y Listo para Entrega - Orden {$codigo}");
                }
            );

            Log::info("Correo de notificación de equipo reparado enviado a {$email} para la orden {$codigo}");
        } catch (Throwable $e) {
            Log::error("Error al enviar correo de orden reparada {$codigo} a {$email}: {$e->getMessage()}");
            throw $e;
        }
    }
}
