<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoOrdenServicio;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $orden_servicio_id
 * @property EstadoOrdenServicio|null $estado_anterior
 * @property EstadoOrdenServicio $estado_nuevo
 * @property string|null $comentario
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property OrdenServicio $ordenServicio
 * @property User $usuario
 */
class OrdenServicioEvento extends Model
{
    protected $table = 'orden_servicio_eventos';

    protected $fillable = [
        'orden_servicio_id',
        'estado_anterior',
        'estado_nuevo',
        'comentario',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'estado_anterior' => EstadoOrdenServicio::class,
            'estado_nuevo' => EstadoOrdenServicio::class,
        ];
    }

    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
