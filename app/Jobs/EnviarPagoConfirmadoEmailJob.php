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

final class EnviarPagoConfirmadoEmailJob implements ShouldQueue
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
        $pedido = Pedido::with(['items.producto', 'venta'])->find($this->pedidoId);

        if (! $pedido || ! $pedido->email_contacto) {
            return;
        }

        $mensaje = "¡Tu pago ha sido confirmado con éxito!\n\n".
            "Código de pedido: {$pedido->codigo}\n".
            "Monto pagado: S/ {$pedido->total}\n".
            "Estado actual: Pagado / En preparación\n\n".
            "Estamos preparando tus productos para su despacho o recojo en tienda según tu elección.\n".
            'Puedes seguir el estado de tu pedido en tiempo real aquí: '.url("/pedido/{$pedido->codigo}")."\n\n".
            "Gracias por confiar en nosotros.\nAtentamente,\nEl equipo de la tienda";

        try {
            Mail::raw($mensaje, function ($m) use ($pedido) {
                $m->to($pedido->email_contacto)
                    ->subject("¡Pago confirmado! Pedido {$pedido->codigo}");
            });
        } catch (Throwable $e) {
            Log::error("Error al enviar email de pago confirmado para pedido {$this->pedidoId}: {$e->getMessage()}");
            throw $e;
        }
    }
}
