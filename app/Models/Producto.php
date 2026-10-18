<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoSerie;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Producto extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'sku',
        'nombre',
        'slug',
        'descripcion',
        'categoria_id',
        'marca_id',
        'modelo',
        'precio_compra_referencial',
        'precio_venta',
        'incluye_igv',
        'controla_series',
        'stock_minimo',
        'imagen',
        'especificaciones',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_compra_referencial' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'incluye_igv' => 'boolean',
            'controla_series' => 'boolean',
            'stock_minimo' => 'integer',
            'especificaciones' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function series(): HasMany
    {
        return $this->hasMany(Serie::class, 'producto_id');
    }

    public function seriesDisponibles(): HasMany
    {
        return $this->hasMany(Serie::class, 'producto_id')->where('estado', EstadoSerie::DISPONIBLE);
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'producto_id');
    }

    public function compraDetalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'producto_id');
    }
}
