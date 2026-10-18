<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoGarantia;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $serie_id
 * @property int $venta_detalle_id
 * @property int $cliente_id
 * @property int|null $politica_garantia_id
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property Carbon $dias_cambio_hasta
 * @property EstadoGarantia $estado
 * @property string|null $condiciones_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Serie|null $serie
 * @property VentaDetalle|null $ventaDetalle
 * @property Cliente|null $cliente
 * @property PoliticaGarantia|null $politicaGarantia
 */
class Garantia extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'garantias';

    protected $fillable = [
        'serie_id',
        'venta_detalle_id',
        'cliente_id',
        'politica_garantia_id',
        'fecha_inicio',
        'fecha_fin',
        'dias_cambio_hasta',
        'estado',
        'condiciones_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'dias_cambio_hasta' => 'date',
            'estado' => EstadoGarantia::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['estado', 'fecha_fin', 'dias_cambio_hasta'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'serie_id');
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class, 'venta_detalle_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function politicaGarantia(): BelongsTo
    {
        return $this->belongsTo(PoliticaGarantia::class, 'politica_garantia_id');
    }

    public function esVigente(): bool
    {
        if ($this->estado !== EstadoGarantia::VIGENTE) {
            return false;
        }

        return Carbon::today()->lte($this->fecha_fin);
    }

    public function diasRestantes(): int
    {
        $hoy = Carbon::today();
        if ($hoy->gt($this->fecha_fin)) {
            return 0;
        }

        return (int) $hoy->diffInDays($this->fecha_fin);
    }

    public function estaEnPlazoCambio(): bool
    {
        return Carbon::today()->lte($this->dias_cambio_hasta);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', EstadoGarantia::VIGENTE)
            ->whereDate('fecha_fin', '>=', Carbon::today());
    }

    public function scopePorVencer(Builder $query): Builder
    {
        return $query->where('estado', EstadoGarantia::VIGENTE)
            ->whereDate('fecha_fin', '<', Carbon::today());
    }
}
