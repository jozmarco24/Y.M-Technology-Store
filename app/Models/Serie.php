<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoSerie;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
