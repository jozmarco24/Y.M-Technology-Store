<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Categoria;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CategoriaService
{
    /**
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos): Categoria
    {
        $this->validar($datos);

        $datos['slug'] = $this->generarSlugUnico($datos['nombre']);

        return Categoria::create($datos);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function actualizar(Categoria $categoria, array $datos): Categoria
    {
        $this->validar($datos, $categoria->id);

        if (isset($datos['nombre']) && $datos['nombre'] !== $categoria->nombre) {
            $datos['slug'] = $this->generarSlugUnico($datos['nombre'], $categoria->id);
        }

        $categoria->update($datos);

        return $categoria->fresh();
    }

    public function desactivar(Categoria $categoria): bool
    {
        return $categoria->update(['activo' => false]);
    }

    public function eliminar(Categoria $categoria): bool
    {
        if ($categoria->productos()->exists()) {
            $categoria->update(['activo' => false]);
            throw new InvalidArgumentException('No se puede eliminar la categoría porque tiene productos asociados. Ha sido desactivada.');
        }

        return (bool) $categoria->delete();
    }

    /**
     * @param array<string, mixed> $datos
     */
    protected function validar(array $datos, ?int $exceptId = null): void
    {
        if (isset($datos['nombre'])) {
            $query = Categoria::query()->where('nombre', $datos['nombre']);
            if ($exceptId !== null) {
                $query->where('id', '!=', $exceptId);
            }
            if ($query->exists()) {
                throw new InvalidArgumentException('Ya existe una categoría con este nombre.');
            }
        }
    }

    protected function generarSlugUnico(string $nombre, ?int $exceptId = null): string
    {
        $baseSlug = Str::slug($nombre);
        $slug = $baseSlug;
        $contador = 1;

        while (true) {
            $query = Categoria::query()->where('slug', $slug);
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
