<?php

declare(strict_types=1);

namespace App\Livewire\Ecommerce;

use App\Models\Carrito;
use App\Models\ZonaEnvio;
use App\Services\CarritoService;
use App\Services\CuponService;
use App\Services\EnvioService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\On;
use Livewire\Component;

class CarritoPagina extends Component
{
    public ?Carrito $carrito = null;

    public string $codigoCupon = '';

    public ?float $descuentoCupon = 0.00;

    public ?string $mensajeCupon = null;

    public string $metodoEntrega = 'recojo_tienda';

    public ?int $zonaEnvioId = null;

    public float $costoEnvio = 0.00;

    public int $diasEstimados = 0;

    /** @var Collection<int, ZonaEnvio> */
    public Collection $zonasEnvio;

    public function mount(CarritoService $carritoService): void
    {
        $this->zonasEnvio = ZonaEnvio::where('activo', true)->get();
        $this->cargarCarrito($carritoService);
    }

    #[On('carrito-actualizado')]
    public function actualizar(CarritoService $carritoService): void
    {
        $this->cargarCarrito($carritoService);
    }

    public function cargarCarrito(CarritoService $carritoService): void
    {
        $clienteWeb = auth('cliente')->user();
        $token = (string) Cookie::get('carrito_token');

        $this->carrito = $carritoService->obtenerOCrear($clienteWeb, $token ?: null);
        $this->carrito->loadMissing(['items.producto.imagenes']);

        $this->recalcularEnvio(app(EnvioService::class));
    }

    public function cambiarCantidad(int $itemId, int $cantidad, CarritoService $carritoService): void
    {
        if ($this->carrito) {
            $carritoService->actualizarCantidad($this->carrito, $itemId, $cantidad);
            $this->cargarCarrito($carritoService);
            $this->dispatch('carrito-actualizado');
        }
    }

    public function quitarItem(int $itemId, CarritoService $carritoService): void
    {
        if ($this->carrito) {
            $carritoService->quitarItem($this->carrito, $itemId);
            $this->cargarCarrito($carritoService);
            $this->dispatch('carrito-actualizado');
            $this->dispatch('notificar', [
                'tipo' => 'info',
                'mensaje' => 'Producto eliminado del carrito.',
            ]);
        }
    }

    public function aplicarCupon(CuponService $cuponService, CarritoService $carritoService): void
    {
        if (! $this->carrito || empty(trim($this->codigoCupon))) {
            return;
        }

        $totales = $carritoService->calcularTotalesYValidar($this->carrito);
        $subtotal = $totales['total'];

        $res = $cuponService->validar($this->codigoCupon, $subtotal);

        if ($res['valido']) {
            $this->descuentoCupon = (float) $res['descuento'];
            $this->mensajeCupon = "¡Cupón aplicado exitosamente! Descuento de S/ {$this->descuentoCupon}";
            $this->dispatch('notificar', [
                'tipo' => 'success',
                'mensaje' => $this->mensajeCupon,
            ]);
        } else {
            $this->descuentoCupon = 0.00;
            $this->mensajeCupon = $res['mensaje'];
            $this->dispatch('notificar', [
                'tipo' => 'warning',
                'mensaje' => $res['mensaje'],
            ]);
        }
    }

    public function updatedMetodoEntrega(): void
    {
        $this->recalcularEnvio(app(EnvioService::class));
    }

    public function updatedZonaEnvioId(): void
    {
        $this->recalcularEnvio(app(EnvioService::class));
    }

    public function recalcularEnvio(EnvioService $envioService): void
    {
        $cotizacion = $envioService->cotizar($this->metodoEntrega, $this->zonaEnvioId);
        $this->costoEnvio = (float) $cotizacion['costo'];
        $this->diasEstimados = (int) $cotizacion['dias_estimados'];
    }

    public function render(CarritoService $carritoService): View
    {
        $totales = $this->carrito ? $carritoService->calcularTotalesYValidar($this->carrito) : [
            'subtotal' => 0.00,
            'total' => 0.00,
            'cambio_precio' => false,
            'cambio_stock' => false,
        ];

        $subtotalProductos = $totales['total'];
        $totalFinal = max(0.00, $subtotalProductos - ($this->descuentoCupon ?? 0.00) + $this->costoEnvio);

        return view('livewire.ecommerce.carrito-pagina', [
            'totales' => $totales,
            'subtotalProductos' => $subtotalProductos,
            'totalFinal' => $totalFinal,
        ])->layout('layouts.ecommerce', [
            'metaTitle' => 'Carrito de Compras | SYS-COPM',
            'metaDescription' => 'Revisa los productos seleccionados, aplica cupones y calcula el costo de envío antes de pagar.',
        ]);
    }
}
