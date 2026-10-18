<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoComprobanteVenta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property TipoComprobanteVenta $tipo_comprobante
 * @property string $serie
 * @property int $correlativo_actual
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SerieComprobante extends Model
{
    use HasFactory;

    protected $table = 'series_comprobantes';

    protected $fillable = [
        'tipo_comprobante',
        'serie',
        'correlativo_actual',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo_comprobante' => TipoComprobanteVenta::class,
            'correlativo_actual' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
