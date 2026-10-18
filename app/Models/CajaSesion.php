<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoCaja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $user_id
 * @property \Carbon\Carbon $abierta_en
 * @property \Carbon\Carbon|null $cerrada_en
 * @property float $monto_apertura
 * @property float|null $monto_cierre_esperado
 * @property float|null $monto_cierre_real
 * @property float|null $diferencia
 * @property \App\Enums\EstadoCaja $estado
 * @property string|null $observaciones
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \App\Models\User|null $usuario
 */
class CajaSesion extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'cajas_sesiones';

    protected $fillable = [
        'user_id',
        'abierta_en',
        'cerrada_en',
        'monto_apertura',
        'monto_cierre_esperado',
        'monto_cierre_real',
        'diferencia',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'abierta_en' => 'datetime',
            'cerrada_en' => 'datetime',
            'monto_apertura' => 'decimal:2',
            'monto_cierre_esperado' => 'decimal:2',
            'monto_cierre_real' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'estado' => EstadoCaja::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->where('estado', EstadoCaja::ABIERTA);
    }

    public function scopeCerradas(Builder $query): Builder
    {
        return $query->where('estado', EstadoCaja::CERRADA);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class, 'caja_sesion_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'caja_sesion_id');
    }
}
