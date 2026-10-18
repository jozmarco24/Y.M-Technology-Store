<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoDocumentoCliente;
use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ClienteService
{
    /**
     * Valida formato de documento según tipo (DNI 8 dígitos, RUC 11 dígitos con dígito verificador).
     */
    public function validarDocumento(TipoDocumentoCliente $tipo, ?string $numero): void
    {
        if ($tipo === TipoDocumentoCliente::SIN_DOC) {
            return;
        }

        if (empty($numero)) {
            throw ValidationException::withMessages([
                'numero_documento' => 'El número de documento es obligatorio para el tipo seleccionado.',
            ]);
        }

        if ($tipo === TipoDocumentoCliente::DNI) {
            if (! preg_match('/^[0-9]{8}$/', $numero)) {
                throw ValidationException::withMessages([
                    'numero_documento' => 'El DNI debe contener exactamente 8 dígitos numéricos.',
                ]);
            }
        } elseif ($tipo === TipoDocumentoCliente::RUC) {
            if (! $this->validarRuc($numero)) {
                throw ValidationException::withMessages([
                    'numero_documento' => 'El RUC no es válido o no cumple con el algoritmo de verificación.',
                ]);
            }
        } elseif ($tipo === TipoDocumentoCliente::CE) {
            if (! preg_match('/^[A-Za-z0-9]{8,12}$/', $numero)) {
                throw ValidationException::withMessages([
                    'numero_documento' => 'El Carné de Extranjería debe tener entre 8 y 12 caracteres alfanuméricos.',
                ]);
            }
        }
    }

    /**
     * Algoritmo módulo 11 para validación de RUC de SUNAT (Perú).
     * Prefijos válidos habituales: 10, 15, 17, 20.
     */
    public function validarRuc(string $ruc): bool
    {
        if (! preg_match('/^[0-9]{11}$/', $ruc)) {
            return false;
        }

        $prefijo = substr($ruc, 0, 2);
        if (! in_array($prefijo, ['10', '15', '16', '17', '20'], true)) {
            return false;
        }

        $factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 10; $i++) {
            $suma += ((int) $ruc[$i]) * $factores[$i];
        }

        $residuo = $suma % 11;
        $digitoCalculado = 11 - $residuo;

        if ($digitoCalculado === 10) {
            $digitoCalculado = 0;
        } elseif ($digitoCalculado === 11) {
            $digitoCalculado = 1;
        }

        return $digitoCalculado === (int) $ruc[10];
    }

    /**
     * Crear cliente con validación previa de documento y unicidad.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Cliente
    {
        $tipoDoc = $datos['tipo_documento'] instanceof TipoDocumentoCliente
            ? $datos['tipo_documento']
            : TipoDocumentoCliente::from($datos['tipo_documento']);

        $numeroDoc = isset($datos['numero_documento']) ? trim((string) $datos['numero_documento']) : null;
        if ($numeroDoc === '') {
            $numeroDoc = null;
        }

        $this->validarDocumento($tipoDoc, $numeroDoc);

        if ($numeroDoc !== null) {
            $existe = Cliente::where('tipo_documento', $tipoDoc->value)
                ->where('numero_documento', $numeroDoc)
                ->exists();

            if ($existe) {
                throw ValidationException::withMessages([
                    'numero_documento' => 'Ya existe un cliente registrado con este documento.',
                ]);
            }
        }

        $datos['tipo_documento'] = $tipoDoc;
        $datos['numero_documento'] = $numeroDoc;
        $datos['activo'] = $datos['activo'] ?? true;

        return Cliente::create($datos);
    }

    /**
     * Actualizar cliente con validación.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Cliente $cliente, array $datos): Cliente
    {
        $tipoDoc = isset($datos['tipo_documento'])
            ? ($datos['tipo_documento'] instanceof TipoDocumentoCliente
                ? $datos['tipo_documento']
                : TipoDocumentoCliente::from($datos['tipo_documento']))
            : $cliente->tipo_documento;

        $numeroDoc = array_key_exists('numero_documento', $datos)
            ? (trim((string) $datos['numero_documento']) !== '' ? trim((string) $datos['numero_documento']) : null)
            : $cliente->numero_documento;

        $this->validarDocumento($tipoDoc, $numeroDoc);

        if ($numeroDoc !== null) {
            $existe = Cliente::where('tipo_documento', $tipoDoc->value)
                ->where('numero_documento', $numeroDoc)
                ->where('id', '!=', $cliente->id)
                ->exists();

            if ($existe) {
                throw ValidationException::withMessages([
                    'numero_documento' => 'Ya existe otro cliente con este número de documento.',
                ]);
            }
        }

        $datos['tipo_documento'] = $tipoDoc;
        $datos['numero_documento'] = $numeroDoc;

        $cliente->update($datos);

        return $cliente->fresh();
    }

    /**
     * Eliminar (soft delete) cliente.
     */
    public function eliminar(Cliente $cliente): bool
    {
        return (bool) $cliente->delete();
    }

    /**
     * Buscar cliente por tipo y número de documento.
     */
    public function buscarPorDocumento(string $numero, ?TipoDocumentoCliente $tipo = null): ?Cliente
    {
        $query = Cliente::where('numero_documento', $numero);

        if ($tipo !== null) {
            $query->where('tipo_documento', $tipo->value);
        }

        return $query->first();
    }

    /**
     * Listar clientes activos.
     *
     * @return Collection<int, Cliente>
     */
    public function listarActivos(): Collection
    {
        return Cliente::activos()->orderBy('nombre_razon_social')->get();
    }

    /**
     * Paginación con filtros de búsqueda.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = Cliente::query();

        if (! empty($filtros['busqueda'])) {
            $query->buscar((string) $filtros['busqueda']);
        }

        if (! empty($filtros['tipo_documento'])) {
            $query->where('tipo_documento', $filtros['tipo_documento']);
        }

        if (isset($filtros['activo'])) {
            $query->where('activo', (bool) $filtros['activo']);
        }

        return $query->orderBy('nombre_razon_social')->paginate($porPagina);
    }
}
