<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reclamacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReclamacionService
{
    /**
     * Registrar una reclamación del Libro de Reclamaciones virtual generando código correlativo.
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrar(array $datos): Reclamacion
    {
        return DB::transaction(function () use ($datos) {
            $ultimoId = DB::table('reclamaciones')->max('id') ?? 0;
            $siguienteNumero = $ultimoId + 1;
            $codigo = sprintf('REC-%06d', $siguienteNumero);

            /** @var Reclamacion $reclamacion */
            $reclamacion = Reclamacion::create([
                'codigo' => $codigo,
                'tipo' => $datos['tipo'] ?? 'reclamo',
                'consumidor_nombre' => trim((string) $datos['consumidor_nombre']),
                'consumidor_tipo_documento' => $datos['consumidor_tipo_documento'] ?? 'DNI',
                'consumidor_numero_documento' => trim((string) $datos['consumidor_numero_documento']),
                'consumidor_telefono' => trim((string) $datos['consumidor_telefono']),
                'consumidor_email' => trim((string) $datos['consumidor_email']),
                'consumidor_domicilio' => trim((string) $datos['consumidor_domicilio']),
                'es_menor_edad' => (bool) ($datos['es_menor_edad'] ?? false),
                'apoderado_nombre' => isset($datos['apoderado_nombre']) ? trim((string) $datos['apoderado_nombre']) : null,
                'apoderado_telefono' => isset($datos['apoderado_telefono']) ? trim((string) $datos['apoderado_telefono']) : null,
                'apoderado_email' => isset($datos['apoderado_email']) ? trim((string) $datos['apoderado_email']) : null,
                'bien_contratado' => $datos['bien_contratado'] ?? 'producto',
                'monto_reclamado' => isset($datos['monto_reclamado']) && $datos['monto_reclamado'] !== '' ? (float) $datos['monto_reclamado'] : null,
                'descripcion_bien' => isset($datos['descripcion_bien']) ? trim((string) $datos['descripcion_bien']) : null,
                'detalle_reclamacion' => trim((string) $datos['detalle_reclamacion']),
                'pedido_consumidor' => trim((string) $datos['pedido_consumidor']),
                'estado' => 'pendiente',
            ]);

            return $reclamacion;
        });
    }

    /**
     * Responder a una reclamación.
     */
    public function responder(Reclamacion $reclamacion, string $respuesta, int $userId, string $nuevoEstado = 'atendido'): Reclamacion
    {
        if (trim($respuesta) === '') {
            throw new InvalidArgumentException('La respuesta no puede estar vacía.');
        }

        $reclamacion->update([
            'respuesta_proveedor' => trim($respuesta),
            'estado' => $nuevoEstado,
            'respondido_en' => Carbon::now(),
            'respondido_por' => $userId,
        ]);

        return $reclamacion->fresh();
    }
}
