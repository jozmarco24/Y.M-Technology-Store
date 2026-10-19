<?php

declare(strict_types=1);

namespace App\Livewire\Ecommerce;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Services\CarritoService;
use App\Services\CatalogoWebService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class Catalogo extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public ?string $categoria = null;

    public ?string $marca = null;

    public ?float $precioMin = null;

    public ?float $precioMax = null;

    public string $orden = 'novedad';

    public ?string $filtroRam = null;

    public ?string $filtroProcesador = null;

    /** @var array<int, int> */
    public array $comparadorIds = [];

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'busqueda' => ['except' => ''],
        'categoria' => ['except' => null],
        'marca' => ['except' => null],
        'orden' => ['except' => 'novedad'],
    ];

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingCategoria(): void
    {
        $this->resetPage();
    }

    public function updatingMarca(): void
    {
        $this->resetPage();
    }

    public function updatingOrden(): void
    {
        $this->resetPage();
    }

    public function toggleComparador(int $productoId): void
    {
        if (in_array($productoId, $this->comparadorIds, true)) {
            $this->comparadorIds = array_values(array_filter($this->comparadorIds, fn ($id) => $id !== $productoId));
        } else {
            if (count($this->comparadorIds) >= 3) {
                $this->dispatch('notificar', [
                    'tipo' => 'warning',
                    'mensaje' => 'Solo puedes comparar hasta 3 productos a la vez.',
                ]);

                return;
            }
            $this->comparadorIds[] = $productoId;
        }
    }

    public function limpiarComparador(): void
    {
        $this->comparadorIds = [];
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

    public function render(CatalogoWebService $catalogoService): View
    {
        $filtros = [
            'busqueda' => $this->busqueda,
            'categoria' => $this->categoria,
            'marca' => $this->marca,
            'precio_min' => $this->precioMin,
            'precio_max' => $this->precioMax,
            'orden' => $this->orden,
            'filtro_ram' => $this->filtroRam,
            'filtro_procesador' => $this->filtroProcesador,
        ];

        /** @var LengthAwarePaginator<Producto> $productos */
        $productos = $catalogoService->listarPublicados($filtros, 12);

        /** @var Collection<int, Categoria> $categorias */
        $categorias = Categoria::where('activo', true)->orderBy('orden')->get();

        /** @var Collection<int, Marca> $marcas */
        $marcas = Marca::where('activo', true)->orderBy('nombre')->get();

        $productosComparar = [];
        if (! empty($this->comparadorIds)) {
            $productosComparar = Producto::publicados()
                ->whereIn('id', $this->comparadorIds)
                ->get();
        }

        return view('livewire.ecommerce.catalogo', [
            'productos' => $productos,
            'categorias' => $categorias,
            'marcas' => $marcas,
            'productosComparar' => $productosComparar,
        ])->layout('layouts.ecommerce', [
            'metaTitle' => 'Catálogo de Productos | SYS-COPM',
            'metaDescription' => 'Explora nuestro catálogo con filtros por marca, precio y especificaciones. Garantía y stock garantizado.',
        ]);
    }
}
