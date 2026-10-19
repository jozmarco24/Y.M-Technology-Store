<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class EnviarPedidoEnviadoEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $pedidoId
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $pedido = Pedido::find($this->pedidoId);

        if (! $pedido || ! $pedido->email_contacto) {
            return;
        }

        $mensaje = "¡Tu pedido está en camino!\n\n".
            "Código de pedido: {$pedido->codigo}\n".
            "Empresa de Courier: {$pedido->courier}\n".
            "Código de Tracking: {$pedido->tracking_codigo}\n\n".
            'Puedes verificar el estado en: '.url("/pedido/{$pedido->codigo}")."\n\n".
            "¡Pronto recibirás tu compra!\nAtentamente,\nEl equipo de la tienda";

        try {
            Mail::raw($mensaje, function ($m) use ($pedido) {
                $m->to($pedido->email_contacto)
                    ->subject("Tu pedido {$pedido->codigo} ha sido enviado");
            });
        } catch (Throwable $e) {
            Log::error("Error al enviar email de pedido enviado {$this->pedidoId}: {$e->getMessage()}");
            throw $e;
        }
    }
}
