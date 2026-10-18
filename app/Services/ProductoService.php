<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductoService
{
    /**
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos): Producto
    {
        $this->validar($datos);

        $datos['slug'] = $this->generarSlugUnico($datos['nombre']);

        return Producto::create($datos);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function actualizar(Producto $producto, array $datos): Producto
    {
        $this->validar($datos, $producto->id);

        if (isset($datos['nombre']) && $datos['nombre'] !== $producto->nombre) {
            $datos['slug'] = $this->generarSlugUnico($datos['nombre'], $producto->id);
        }

        $producto->update($datos);

        return $producto->fresh();
    }

    public function desactivar(Producto $producto): bool
    {
        return $producto->update(['activo' => false]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    protected function validar(array $datos, ?int $exceptId = null): void
    {
        if (isset($datos['precio_venta']) && (float) $datos['precio_venta'] <= 0) {
            throw new InvalidArgumentException('El precio de venta debe ser mayor a cero.');
        }

        if (isset($datos['sku'])) {
            $query = Producto::query()->where('sku', $datos['sku']);
            if ($exceptId !== null) {
                $query->where('id', '!=', $exceptId);
            }
            if ($query->exists()) {
                throw new InvalidArgumentException('El SKU ya se encuentra registrado.');
            }
        }
    }

    protected function generarSlugUnico(string $nombre, ?int $exceptId = null): string
    {
        $baseSlug = Str::slug($nombre);
        $slug = $baseSlug;
        $contador = 1;

        while (true) {
            $query = Producto::query()->where('slug', $slug);
            if ($exceptId !== null) {
                $query->where('id', '!=', $exceptId);
            }

            if (! $query->exists()) {
                return $slug;
            }

            $slug = "{$baseSlug}-{$contador}";
            $contador++;
        }
    }
}
