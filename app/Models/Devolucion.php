<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoDevolucion;
use App\Enums\MetodoPago;
use App\Enums\MotivoDevolucion;
use App\Enums\TipoDevolucion;
use Carbon\Carbon;
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
 * @property int $venta_id
 * @property int $cliente_id
 * @property TipoDevolucion $tipo
 * @property MotivoDevolucion $motivo
 * @property string $detalle_motivo
 * @property EstadoDevolucion $estado
 * @property string $total_devuelto
 * @property MetodoPago|null $metodo_reembolso
 * @property int $solicitado_por
 * @property int|null $aprobado_por
 * @property Carbon|null $aprobado_en
 * @property Carbon|null $completada_en
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Venta|null $venta
 * @property Cliente|null $cliente
 * @property User|null $solicitante
 * @property User|null $aprobador
 */
class Devolucion extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'devoluciones';

    protected $fillable = [
        'codigo',
        'venta_id',
        'cliente_id',
        'tipo',
        'motivo',
        'detalle_motivo',
        'estado',
        'total_devuelto',
        'metodo_reembolso',
        'solicitado_por',
        'aprobado_por',
        'aprobado_en',
        'completada_en',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDevolucion::class,
            'motivo' => MotivoDevolucion::class,
            'estado' => EstadoDevolucion::class,
            'metodo_reembolso' => MetodoPago::class,
            'total_devuelto' => 'decimal:2',
            'aprobado_en' => 'datetime',
            'completada_en' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['codigo', 'estado', 'total_devuelto', 'tipo', 'motivo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DevolucionDetalle::class, 'devolucion_id');
    }

    public function notaCredito(): HasOne
    {
        return $this->hasOne(NotaCredito::class, 'devolucion_id');
    }

    public function cambiosSerie(): HasMany
    {
        return $this->hasMany(CambioSerie::class, 'devolucion_id');
    }

    public function esSolicitada(): bool
    {
        return $this->estado === EstadoDevolucion::SOLICITADA;
    }

    public function esAprobada(): bool
    {
        return $this->estado === EstadoDevolucion::APROBADA;
    }

    public function esCompletada(): bool
    {
        return $this->estado === EstadoDevolucion::COMPLETADA;
    }

    public function esRechazada(): bool
    {
        return $this->estado === EstadoDevolucion::RECHAZADA;
    }
}
