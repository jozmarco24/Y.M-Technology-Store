<?php

declare(strict_types=1);

namespace App\Livewire\Ecommerce;

use App\Models\PoliticaGarantia;
use App\Models\Producto;
use App\Services\CarritoService;
use App\Services\CatalogoWebService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Livewire\Component;

class DetalleProducto extends Component
{
    public Producto $producto;

    public int $cantidad = 1;

    public string $imagenSeleccionada = '';

    public ?PoliticaGarantia $politicaGarantia = null;

    /** @var Collection<int, Producto> */
    public Collection $relacionados;

    public function mount(string $slug, CatalogoWebService $catalogoService): void
    {
        $this->producto = $catalogoService->obtenerPorSlug($slug);
        $this->imagenSeleccionada = $this->producto->imagen ?? '';

        // Buscar política de garantía para la categoría
        $this->politicaGarantia = PoliticaGarantia::where('categoria_id', $this->producto->categoria_id)
            ->where('activo', true)
            ->first();

        $this->relacionados = $catalogoService->obtenerRelacionados($this->producto, 4);
    }

    public function seleccionarImagen(string $ruta): void
    {
        $this->imagenSeleccionada = $ruta;
    }

    public function incrementarCantidad(): void
    {
        if ($this->cantidad < (int) ($this->producto->stock_disponible ?? 1)) {
            $this->cantidad++;
        }
    }

    public function decrementarCantidad(): void
    {
        if ($this->cantidad > 1) {
            $this->cantidad--;
        }
    }

    public function agregarAlCarrito(CarritoService $carritoService): void
    {
        $clienteWeb = auth('cliente')->user();
        $token = (string) Cookie::get('carrito_token');

        if (! $clienteWeb && ! $token) {
            $token = (string) Str::uuid();
            Cookie::queue('carrito_token', $token, 60 * 24 * 30);
        }

        $carrito = $carritoService->obtenerOCrear($clienteWeb, $token);
        $carritoService->agregarProducto($carrito, $this->producto, $this->cantidad);

        $this->dispatch('carrito-actualizado');
        $this->dispatch('notificar', [
            'tipo' => 'success',
            'mensaje' => "¡{$this->cantidad}x {$this->producto->nombre} agregado al carrito!",
        ]);
    }

    public function render(): View
    {
        return view('livewire.ecommerce.detalle-producto')
            ->layout('layouts.ecommerce', [
                'metaTitle' => ($this->producto->meta_titulo ?: $this->producto->nombre).' | SYS-COPM',
                'metaDescription' => $this->producto->meta_descripcion ?: Str::limit(strip_tags((string) $this->producto->descripcion), 150),
            ]);
    }
}
