<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoSerie;
use App\Models\Carrito;
use App\Models\CarritoItem;
use App\Models\ClienteWeb;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CarritoService
{
    /**
     * Obtener o crear un carrito por cliente web autenticado o por token de invitado.
     */
    public function obtenerOCrear(?ClienteWeb $clienteWeb = null, ?string $token = null): Carrito
    {
        if ($clienteWeb) {
            $carrito = Carrito::where('cliente_web_id', $clienteWeb->id)
                ->with(['items.producto'])
                ->latest('id')
                ->first();

            if ($carrito) {
                return $carrito;
            }
        }

        if ($token) {
            $carrito = Carrito::where('token', $token)
                ->with(['items.producto'])
                ->first();

            if ($carrito) {
                return $carrito;
            }
        }

        $nuevoToken = (string) Str::uuid();

        return Carrito::create([
            'cliente_web_id' => $clienteWeb?->id,
            'token' => $token ?: $nuevoToken,
            'expira_en' => Carbon::now()->addDays(30),
        ]);
    }

    /**
     * Agregar un producto al carrito revalidando stock disponible y precio vigente.
     */
    public function agregarProducto(Carrito $carrito, Producto $producto, int $cantidad = 1): CarritoItem
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        if (! $producto->activo || ! $producto->publicado_web) {
            throw new InvalidArgumentException("El producto '{$producto->nombre}' no está disponible para compra web.");
        }

        // Consultar stock real disponible
        $stockDisponible = $producto->series()
            ->where('estado', EstadoSerie::DISPONIBLE)
            ->count();

        return DB::transaction(function () use ($carrito, $producto, $cantidad, $stockDisponible) {
            $item = CarritoItem::where('carrito_id', $carrito->id)
                ->where('producto_id', $producto->id)
                ->first();

            $cantidadDeseada = $item ? ($item->cantidad + $cantidad) : $cantidad;

            if ($cantidadDeseada > $stockDisponible) {
                throw new InvalidArgumentException("Stock insuficiente para '{$producto->nombre}'. Disponible: {$stockDisponible}, solicitado: {$cantidadDeseada}.");
            }

            if ($item) {
                $item->update([
                    'cantidad' => $cantidadDeseada,
                    'precio_unitario' => $producto->precio_venta, // Revalidar precio actual
                ]);

                return $item->fresh();
            }

            return CarritoItem::create([
                'carrito_id' => $carrito->id,
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $producto->precio_venta,
            ]);
        });
    }

    /**
     * Actualizar la cantidad de un ítem en el carrito revalidando stock real.
     */
    public function actualizarCantidad(Carrito $carrito, int $carritoItemId, int $nuevaCantidad): ?CarritoItem
    {
        if ($nuevaCantidad <= 0) {
            $this->quitarItem($carrito, $carritoItemId);

            return null;
        }

        $item = CarritoItem::where('carrito_id', $carrito->id)
            ->where('id', $carritoItemId)
            ->with('producto')
            ->firstOrFail();

        $stockDisponible = $item->producto->series()
            ->where('estado', EstadoSerie::DISPONIBLE)
            ->count();

        if ($nuevaCantidad > $stockDisponible) {
            throw new InvalidArgumentException("Stock insuficiente. Disponible: {$stockDisponible}, solicitado: {$nuevaCantidad}.");
        }

        $item->update([
            'cantidad' => $nuevaCantidad,
            'precio_unitario' => $item->producto->precio_venta,
        ]);

        return $item->fresh();
    }

    /**
     * Quitar un ítem del carrito.
     */
    public function quitarItem(Carrito $carrito, int $carritoItemId): void
    {
        CarritoItem::where('carrito_id', $carrito->id)
            ->where('id', $carritoItemId)
            ->delete();
    }

    /**
     * Vaciar completamente el carrito.
     */
    public function vaciar(Carrito $carrito): void
    {
        $carrito->items()->delete();
    }

    /**
     * Fusionar carrito de invitado al iniciar sesión el cliente web.
     */
    public function fusionarCarritoInvitado(string $tokenInvitado, ClienteWeb $clienteWeb): Carrito
    {
        $carritoInvitado = Carrito::where('token', $tokenInvitado)
            ->with(['items.producto'])
            ->first();

        $carritoCliente = $this->obtenerOCrear($clienteWeb);

        if (! $carritoInvitado || $carritoInvitado->items->isEmpty()) {
            return $carritoCliente;
        }

        DB::transaction(function () use ($carritoInvitado, $carritoCliente) {
            foreach ($carritoInvitado->items as $itemInvitado) {
                $itemDestino = CarritoItem::where('carrito_id', $carritoCliente->id)
                    ->where('producto_id', $itemInvitado->producto_id)
                    ->first();

                $stockDisponible = $itemInvitado->producto->series()
                    ->where('estado', EstadoSerie::DISPONIBLE)
                    ->count();

                if ($itemDestino) {
                    $nuevaCantidad = min($stockDisponible, $itemDestino->cantidad + $itemInvitado->cantidad);
                    $itemDestino->update([
                        'cantidad' => $nuevaCantidad,
                        'precio_unitario' => $itemInvitado->producto->precio_venta,
                    ]);
                } else {
                    $cantidad = min($stockDisponible, $itemInvitado->cantidad);
                    if ($cantidad > 0) {
                        CarritoItem::create([
                            'carrito_id' => $carritoCliente->id,
                            'producto_id' => $itemInvitado->producto_id,
                            'cantidad' => $cantidad,
                            'precio_unitario' => $itemInvitado->producto->precio_venta,
                        ]);
                    }
                }
            }

            // Eliminar carrito invitado tras la fusión
            $carritoInvitado->items()->delete();
            $carritoInvitado->delete();
        });

        return $carritoCliente->fresh(['items.producto']);
    }

    /**
     * Validar el carrito contra precios vigentes y stock actual, calculando subtotales.
     *
     * @return array{
     *     valido: bool,
     *     items: array<int, array{id: int, producto_id: int, nombre: string, cantidad: int, precio_vigente: float, subtotal: float, stock_disponible: int, con_stock: bool}>,
     *     subtotal: float,
     *     igv: float,
     *     total: float,
     *     errores: array<int, string>
     * }
     */
    public function calcularTotalesYValidar(Carrito $carrito): array
    {
        $carrito->loadMissing(['items.producto.series']);

        $itemsCalculados = [];
        $subtotal = 0.0;
        $errores = [];
        $valido = true;

        foreach ($carrito->items as $item) {
            $producto = $item->producto;
            $precioVigente = (float) $producto->precio_venta;
            $stockDisponible = $producto->series->where('estado', EstadoSerie::DISPONIBLE)->count();

            $tieneStock = $stockDisponible >= $item->cantidad;
            if (! $tieneStock) {
                $valido = false;
                $errores[] = "El producto '{$producto->nombre}' solo tiene {$stockDisponible} unidades disponibles.";
            }

            if (! $producto->activo || ! $producto->publicado_web) {
                $valido = false;
                $errores[] = "El producto '{$producto->nombre}' ya no está disponible para la venta.";
            }

            // Actualizar si el precio varió
            if (round((float) $item->precio_unitario, 2) !== round($precioVigente, 2)) {
                $item->update(['precio_unitario' => $precioVigente]);
            }

            $lineaSubtotal = round($item->cantidad * $precioVigente, 2);
            $subtotal += $lineaSubtotal;

            $itemsCalculados[] = [
                'id' => $item->id,
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'cantidad' => $item->cantidad,
                'precio_vigente' => $precioVigente,
                'subtotal' => $lineaSubtotal,
                'stock_disponible' => $stockDisponible,
                'con_stock' => $tieneStock,
            ];
        }

        $subtotal = round($subtotal, 2);
        // Precios ya incluyen IGV (18%)
        $baseImponible = round($subtotal / 1.18, 2);
        $igv = round($subtotal - $baseImponible, 2);

        return [
            'valido' => $valido && count($itemsCalculados) > 0,
            'items' => $itemsCalculados,
            'subtotal' => $subtotal,
            'base_imponible' => $baseImponible,
            'igv' => $igv,
            'total' => $subtotal,
            'errores' => $errores,
        ];
    }
}
