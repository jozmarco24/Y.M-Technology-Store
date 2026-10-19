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

final class EnviarPedidoEntregadoEmailJob implements ShouldQueue
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

        $mensaje = "¡Tu pedido ha sido entregado exitosamente!\n\n".
            "Código de pedido: {$pedido->codigo}\n\n".
            "Esperamos que disfrutes de tu compra. Recuerda que todos tus productos cuentan con garantía oficial de la tienda.\n".
            'Puedes consultar el estado y la vigencia de tus garantías en nuestra plataforma web: '.url('/mi-cuenta/garantias')."\n\n".
            "¡Gracias por tu preferencia!\nAtentamente,\nEl equipo de la tienda";

        try {
            Mail::raw($mensaje, function ($m) use ($pedido) {
                $m->to($pedido->email_contacto)
                    ->subject("Tu pedido {$pedido->codigo} ha sido entregado");
            });
        } catch (Throwable $e) {
            Log::error("Error al enviar email de pedido entregado {$this->pedidoId}: {$e->getMessage()}");
            throw $e;
        }
    }
}
