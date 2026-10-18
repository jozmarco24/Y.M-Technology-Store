<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MomentoFoto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $orden_servicio_id
 * @property string $ruta
 * @property string|null $descripcion
 * @property MomentoFoto $momento
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property OrdenServicio $ordenServicio
 */
class OrdenServicioFoto extends Model
{
    protected $table = 'orden_servicio_fotos';

    protected $fillable = [
        'orden_servicio_id',
        'ruta',
        'descripcion',
        'momento',
    ];

    protected function casts(): array
    {
        return [
            'momento' => MomentoFoto::class,
        ];
    }

    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id');
    }
}
