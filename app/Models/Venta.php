<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoVenta;
use App\Enums\TipoComprobanteVenta;
use BadMethodCallException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $codigo
 * @property int $cliente_id
 * @property int $vendedor_id
 * @property int $caja_sesion_id
 * @property TipoComprobanteVenta $tipo_comprobante
 * @property string $serie_comprobante
 * @property int $numero_comprobante
 * @property float $subtotal
 * @property float $descuento
 * @property float $igv
 * @property float $total
 * @property EstadoVenta $estado
 * @property string|null $observaciones
 * @property int|null $anulada_por
 * @property Carbon|null $anulada_en
 * @property string|null $motivo_anulacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Cliente|null $cliente
 * @property User|null $vendedor
 * @property User|null $anulador
 * @property CajaSesion|null $cajaSesion
 */
class Venta extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'ventas';

    protected $fillable = [
        'codigo',
        'cliente_id',
        'vendedor_id',
        'caja_sesion_id',
        'tipo_comprobante',
        'serie_comprobante',
        'numero_comprobante',
        'subtotal',
        'descuento',
        'igv',
        'total',
        'estado',
        'observaciones',
        'anulada_por',
        'anulada_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'tipo_comprobante' => TipoComprobanteVenta::class,
            'numero_comprobante' => 'integer',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'estado' => EstadoVenta::class,
            'anulada_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Venta $venta) {
            // Una venta nunca se edita después de registrada, salvo para ser anulada
            $dirty = array_keys($venta->getDirty());
            $permitidosAnulacion = ['estado', 'anulada_por', 'anulada_en', 'motivo_anulacion', 'observaciones', 'updated_at'];

            $diferencias = array_diff($dirty, $permitidosAnulacion);
            if (! empty($diferencias)) {
                throw new BadMethodCallException('Una venta completada es inmutable y no se pueden modificar sus datos comerciales ni montos.');
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeCompletadas(Builder $query): Builder
    {
        return $query->where('estado', EstadoVenta::COMPLETADA);
    }

    public function scopeAnuladas(Builder $query): Builder
    {
        return $query->where('estado', EstadoVenta::ANULADA);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    public function cajaSesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class, 'caja_sesion_id');
    }

    public function esCompletada(): bool
    {
        return $this->estado === EstadoVenta::COMPLETADA;
    }

    public function esAnulada(): bool
    {
        return $this->estado === EstadoVenta::ANULADA;
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class, 'venta_id');
    }

    public function comprobanteElectronico(): HasOne
    {
        return $this->hasOne(ComprobanteElectronico::class, 'venta_id');
    }
}
