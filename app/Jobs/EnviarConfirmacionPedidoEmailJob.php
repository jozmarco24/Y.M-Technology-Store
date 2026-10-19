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

final class EnviarConfirmacionPedidoEmailJob implements ShouldQueue
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
        $pedido = Pedido::with(['items.producto'])->find($this->pedidoId);

        if (! $pedido || ! $pedido->email_contacto) {
            return;
        }

        $itemsTexto = '';
        foreach ($pedido->items as $item) {
            $itemsTexto .= "- {$item->descripcion} x {$item->cantidad} : S/ {$item->subtotal}\n";
        }

        $mensaje = "Hola,\n\n".
            "Gracias por tu pedido en nuestra tienda online.\n".
            "Código de pedido: {$pedido->codigo}\n".
            "Estado: {$pedido->estado->etiqueta()}\n".
            "Método de entrega: {$pedido->metodo_entrega->etiqueta()}\n\n".
            "Detalle de productos:\n{$itemsTexto}\n".
            "Subtotal: S/ {$pedido->subtotal}\n".
            "Costo de envío: S/ {$pedido->costo_envio}\n".
            "Descuento: S/ {$pedido->descuento}\n".
            "Total a pagar: S/ {$pedido->total}\n\n".
            'Puedes consultar el estado en cualquier momento en: '.url("/pedido/{$pedido->codigo}")."\n\n".
            "Atentamente,\nEl equipo de la tienda";

        try {
            Mail::raw($mensaje, function ($m) use ($pedido) {
                $m->to($pedido->email_contacto)
                    ->subject("Confirmación de Pedido {$pedido->codigo}");
            });
        } catch (Throwable $e) {
            Log::error("Error al enviar email de confirmación de pedido {$this->pedidoId}: {$e->getMessage()}");
            throw $e;
        }
    }
}
