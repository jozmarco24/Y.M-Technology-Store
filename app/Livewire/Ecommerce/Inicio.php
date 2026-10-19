<?php

declare(strict_types=1);

namespace App\Livewire\Ecommerce;

use App\Models\Banner;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Services\CarritoService;
use App\Services\CatalogoWebService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Livewire\Component;

class Inicio extends Component
{
    /** @var Collection<int, Banner> */
    public Collection $banners;

    /** @var Collection<int, Categoria> */
    public Collection $categoriasDestacadas;

    /** @var Collection<int, Producto> */
    public Collection $productosDestacados;

    /** @var Collection<int, Producto> */
    public Collection $ofertas;

    /** @var Collection<int, Marca> */
    public Collection $marcas;

    public function mount(CatalogoWebService $catalogoService): void
    {
        $this->banners = Banner::where('activo', true)->orderBy('orden')->get();

        $this->categoriasDestacadas = Categoria::where('activo', true)
            ->orderBy('orden')
            ->take(6)
            ->get();

        $this->productosDestacados = Producto::publicados()
            ->with(['categoria', 'marca', 'imagenes'])
            ->where('destacado', true)
            ->take(8)
            ->get();

        $this->ofertas = Producto::publicados()
            ->with(['categoria', 'marca', 'imagenes'])
            ->latest('id')
            ->take(8)
            ->get();

        $this->marcas = Marca::where('activo', true)->take(8)->get();
    }

    public function agregarAlCarrito(int $productoId, CarritoService $carritoService): void
    {
        $producto = Producto::publicados()->findOrFail($productoId);

        $clienteWeb = auth('cliente')->user();
        $token = (string) Cookie::get('carrito_token');

        if (! $clienteWeb && ! $token) {
            $token = (string) Str::uuid();
            Cookie::queue('carrito_token', $token, 60 * 24 * 30);
        }

        $carrito = $carritoService->obtenerOCrear($clienteWeb, $token);
        $carritoService->agregarProducto($carrito, $producto, 1);

        $this->dispatch('carrito-actualizado');
        $this->dispatch('notificar', [
            'tipo' => 'success',
            'mensaje' => "¡{$producto->nombre} agregado al carrito!",
        ]);
    }

    public function render(): View
    {
        return view('livewire.ecommerce.inicio')
            ->layout('layouts.ecommerce', [
                'metaTitle' => 'Tienda de Tecnología y Cómputo | SYS-COPM',
                'metaDescription' => 'Encuentra las mejores laptops, componentes, periféricos y accesorios con garantía oficial y entrega rápida.',
            ]);
    }
}
