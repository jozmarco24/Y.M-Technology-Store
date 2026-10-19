<?php

declare(strict_types=1);

namespace App\Livewire\Ecommerce;

use App\Enums\EstadoPedido;
use App\Models\Pedido;
use App\Services\PagoOnlineService;
use Exception;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SeguimientoPedido extends Component
{
    public string $codigo = '';

    public string $email = '';

    public ?Pedido $pedido = null;

    public bool $autenticado = false;

    public ?string $mensajeReintento = null;

    public function mount(string $codigo): void
    {
        $this->codigo = strtoupper($codigo);
        $this->buscarPedido();
    }

    public function buscarPedido(): void
    {
        $pedidoEncontrado = Pedido::with([
            'items.producto',
            'items.itemSeries.serie',
            'eventos',
            'pagosOnline',
            'venta.comprobanteElectronico',
        ])->where('codigo', $this->codigo)->first();

        if (! $pedidoEncontrado) {
            $this->pedido = null;

            return;
        }

        // Si el cliente está logueado y es dueño del pedido:
        $clienteLogueado = auth('cliente')->user();
        if ($clienteLogueado && $pedidoEncontrado->cliente_web_id === $clienteLogueado->id) {
            $this->pedido = $pedidoEncontrado;
            $this->autenticado = true;

            return;
        }

        // Si es invitado y ya validó email o el email coincide
        if ($this->email !== '' && strtolower(trim($this->email)) === strtolower(trim($pedidoEncontrado->email_contacto))) {
            $this->pedido = $pedidoEncontrado;
            $this->autenticado = true;

            return;
        }

        // Requiere confirmar email para ver el detalle si es invitado
        $this->pedido = null;
        $this->autenticado = false;
    }

    public function verificarAccesoEmail(): void
    {
        $this->validate([
            'email' => 'required|email',
        ]);

        $this->buscarPedido();

        if (! $this->autenticado) {
            $this->addError('email', 'El correo ingresado no coincide con el registrado en este pedido.');
        }
    }

    public function reintentarPago(PagoOnlineService $pagoOnlineService): void
    {
        if (! $this->pedido || $this->pedido->estado !== EstadoPedido::PENDIENTE_PAGO) {
            return;
        }

        if ($this->pedido->estaExpirado()) {
            $this->mensajeReintento = 'La reserva de este pedido ha expirado. Por favor, crea un nuevo pedido desde el carrito.';

            return;
        }

        try {
            $pago = $this->pedido->pagosOnline()->latest()->first();
            if ($pago) {
                $pagoOnlineService->confirmar($pago, ['referencia' => 'REINTENTO-SIMULADO']);
                $this->buscarPedido();
                $this->mensajeReintento = '¡Pago reintentado y confirmado con éxito!';
            }
        } catch (Exception $e) {
            $this->mensajeReintento = 'Error al procesar el pago: '.$e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.ecommerce.seguimiento-pedido')
            ->layout('layouts.ecommerce', [
                'metaTitle' => "Seguimiento de Pedido {$this->codigo} | SYS-COPM",
                'metaDescription' => "Consulta el estado en vivo y seguimiento de despacho del pedido {$this->codigo}.",
            ]);
    }
}
