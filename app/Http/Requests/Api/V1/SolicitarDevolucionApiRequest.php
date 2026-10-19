<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\EstadoFisico;
use App\Enums\MotivoDevolucion;
use App\Enums\TipoDevolucion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitarDevolucionApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('devoluciones.solicitar') || $this->user()?->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'venta_id' => ['required', 'integer', 'exists:ventas,id'],
            'tipo' => ['required', Rule::enum(TipoDevolucion::class)],
            'motivo' => ['required', Rule::enum(MotivoDevolucion::class)],
            'detalle_motivo' => ['required', 'string', 'min:5'],
            'metodo_reembolso' => ['nullable', 'string', 'in:efectivo,yape,plin,tarjeta,transferencia'],
            'aprobacion_excepcional_admin' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.venta_detalle_id' => ['required', 'integer', 'exists:venta_detalles,id'],
            'items.*.serie_id' => ['nullable', 'integer', 'exists:series,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.monto_devuelto' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.estado_fisico' => ['required', Rule::enum(EstadoFisico::class)],
            'items.*.destino_serie' => ['nullable', 'string', 'in:disponible,en_garantia,baja'],
        ];
    }
}
