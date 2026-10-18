<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Marca;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MarcaService
{
    /**
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos): Marca
    {
        $this->validar($datos);

        $datos['slug'] = $this->generarSlugUnico($datos['nombre']);

        return Marca::create($datos);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function actualizar(Marca $marca, array $datos): Marca
    {
        $this->validar($datos, $marca->id);

        if (isset($datos['nombre']) && $datos['nombre'] !== $marca->nombre) {
            $datos['slug'] = $this->generarSlugUnico($datos['nombre'], $marca->id);
        }

        $marca->update($datos);

        return $marca->fresh();
    }

    public function desactivar(Marca $marca): bool
    {
        return $marca->update(['activo' => false]);
    }

    public function eliminar(Marca $marca): bool
    {
        if ($marca->productos()->exists()) {
            $marca->update(['activo' => false]);
            throw new InvalidArgumentException('No se puede eliminar la marca porque tiene productos asociados. Ha sido desactivada.');
        }

        return (bool) $marca->delete();
    }

    /**
     * @param array<string, mixed> $datos
     */
    protected function validar(array $datos, ?int $exceptId = null): void
    {
        if (isset($datos['nombre'])) {
            $query = Marca::query()->where('nombre', $datos['nombre']);
            if ($exceptId !== null) {
                $query->where('id', '!=', $exceptId);
            }
            if ($query->exists()) {
                throw new InvalidArgumentException('Ya existe una marca con este nombre.');
            }
        }
    }

    protected function generarSlugUnico(string $nombre, ?int $exceptId = null): string
    {
        $baseSlug = Str::slug($nombre);
        $slug = $baseSlug;
        $contador = 1;

        while (true) {
            $query = Marca::query()->where('slug', $slug);
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
