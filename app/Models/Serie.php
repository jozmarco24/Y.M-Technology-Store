<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoSerie;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $producto_id
 * @property int|null $compra_detalle_id
 * @property string $numero_serie
 * @property EstadoSerie $estado
 * @property float $costo
 * @property Carbon $fecha_ingreso
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Producto|null $producto
 * @property CompraDetalle|null $compraDetalle
 */
class Serie extends Model
{
    use HasFactory;

    protected $table = 'series';

    protected $fillable = [
        'producto_id',
        'compra_detalle_id',
        'numero_serie',
        'estado',
        'costo',
        'fecha_ingreso',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoSerie::class,
            'costo' => 'decimal:2',
            'fecha_ingreso' => 'datetime',
        ];
    }

    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('estado', EstadoSerie::DISPONIBLE);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function compraDetalle(): BelongsTo
    {
        return $this->belongsTo(CompraDetalle::class, 'compra_detalle_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'serie_id');
    }

    public function ventaDetalle(): HasOne
    {
        return $this->hasOne(VentaDetalle::class, 'serie_id');
    }
}
