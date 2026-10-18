<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoDocumento;
use App\Models\Proveedor;
use InvalidArgumentException;

class ProveedorService
{
    /**
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos): Proveedor
    {
        $this->validar($datos);

        return Proveedor::create($datos);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function actualizar(Proveedor $proveedor, array $datos): Proveedor
    {
        $this->validar($datos, $proveedor->id);

        $proveedor->update($datos);

        return $proveedor->fresh();
    }

    public function desactivar(Proveedor $proveedor): bool
    {
        return $proveedor->update(['activo' => false]);
    }

    public function eliminar(Proveedor $proveedor): bool
    {
        if ($proveedor->compras()->exists()) {
            $proveedor->update(['activo' => false]);
            throw new InvalidArgumentException('No se puede eliminar el proveedor porque tiene compras asociadas. Ha sido desactivado.');
        }

        return (bool) $proveedor->delete();
    }

    /**
     * @param array<string, mixed> $datos
     */
    protected function validar(array $datos, ?int $exceptId = null): void
    {
        $tipoDoc = $datos['tipo_documento'] ?? null;
        if ($tipoDoc instanceof TipoDocumento) {
            $tipoDoc = $tipoDoc->value;
        }

        $numeroDoc = $datos['numero_documento'] ?? null;

        if ($tipoDoc !== null && $numeroDoc !== null) {
            if ($tipoDoc === TipoDocumento::RUC->value) {
                if (! preg_match('/^[0-9]{11}$/', (string) $numeroDoc)) {
                    throw new InvalidArgumentException('El RUC debe tener exactamente 11 dígitos numéricos.');
                }
            } elseif ($tipoDoc === TipoDocumento::DNI->value) {
                if (! preg_match('/^[0-9]{8}$/', (string) $numeroDoc)) {
                    throw new InvalidArgumentException('El DNI debe tener exactamente 8 dígitos numéricos.');
                }
            }
        }

        if ($numeroDoc !== null) {
            $query = Proveedor::query()->where('numero_documento', $numeroDoc);
            if ($exceptId !== null) {
                $query->where('id', '!=', $exceptId);
            }
            if ($query->exists()) {
                throw new InvalidArgumentException('El número de documento ya está registrado para otro proveedor.');
            }
        }
    }
}
