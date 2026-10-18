<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoCompra;
use App\Enums\TipoComprobante;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $codigo
 * @property int $proveedor_id
 * @property TipoComprobante $tipo_comprobante
 * @property string $numero_comprobante
 * @property Carbon $fecha_emision
 * @property string $moneda
 * @property float|null $tipo_cambio
 * @property float $subtotal
 * @property float $igv
 * @property float $total
 * @property EstadoCompra $estado
 * @property string|null $observaciones
 * @property int $creado_por
 * @property int|null $recibido_por
 * @property Carbon|null $recibido_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Compra extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'compras';

    protected $fillable = [
        'codigo',
        'proveedor_id',
        'tipo_comprobante',
        'numero_comprobante',
        'fecha_emision',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'igv',
        'total',
        'estado',
        'observaciones',
        'creado_por',
        'recibido_por',
        'recibido_en',
    ];

    protected function casts(): array
    {
        return [
            'tipo_comprobante' => TipoComprobante::class,
            'fecha_emision' => 'date',
            'tipo_cambio' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'estado' => EstadoCompra::class,
            'recibido_en' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeBorradores(Builder $query): Builder
    {
        return $query->where('estado', EstadoCompra::BORRADOR);
    }

    public function scopeRecibidas(Builder $query): Builder
    {
        return $query->where('estado', EstadoCompra::RECIBIDA);
    }

    public function scopeAnuladas(Builder $query): Builder
    {
        return $query->where('estado', EstadoCompra::ANULADA);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function recibidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }
}
