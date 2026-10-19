<?php

declare(strict_types=1);

namespace App\Livewire\Ecommerce;

use App\Models\Carrito;
use App\Services\CarritoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\On;
use Livewire\Component;

class MiniCarrito extends Component
{
    public ?Carrito $carrito = null;

    public int $cantidadTotal = 0;

    public float $total = 0.00;

    public bool $abierto = false;

    public function mount(CarritoService $carritoService): void
    {
        $this->cargarCarrito($carritoService);
    }

    #[On('carrito-actualizado')]
    public function actualizar(CarritoService $carritoService): void
    {
        $this->cargarCarrito($carritoService);
    }

    public function toggle(): void
    {
        $this->abierto = ! $this->abierto;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function cargarCarrito(CarritoService $carritoService): void
    {
        $clienteWeb = auth('cliente')->user();
        $token = (string) Cookie::get('carrito_token');

        $this->carrito = $carritoService->obtenerOCrear($clienteWeb, $token ?: null);
        $this->carrito->loadMissing(['items.producto']);

        $this->cantidadTotal = (int) $this->carrito->items->sum('cantidad');
        $totales = $carritoService->calcularTotalesYValidar($this->carrito);
        $this->total = $totales['total'];
    }

    public function quitarItem(int $itemId, CarritoService $carritoService): void
    {
        if ($this->carrito) {
            $carritoService->quitarItem($this->carrito, $itemId);
            $this->cargarCarrito($carritoService);
            $this->dispatch('carrito-actualizado');
        }
    }

    public function render(): View
    {
        return view('livewire.ecommerce.mini-carrito');
    }
}
