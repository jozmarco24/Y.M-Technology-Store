<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoOrdenServicio;
use App\Enums\TipoOrdenServicio;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
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
 * @property TipoOrdenServicio $tipo
 * @property int|null $serie_id
 * @property int|null $producto_id
 * @property string|null $equipo_descripcion
 * @property string|null $serie_externa
 * @property int $cliente_id
 * @property int|null $garantia_id
 * @property string $falla_reportada
 * @property string $estado_recepcion
 * @property EstadoOrdenServicio $estado
 * @property int|null $tecnico_id
 * @property string|null $diagnostico
 * @property string|null $solucion
 * @property string|null $costo_estimado
 * @property string|null $costo_final
 * @property bool|null $aprobado_por_cliente
 * @property Carbon|null $aprobado_en
 * @property Carbon|null $fecha_promesa
 * @property int $recibido_por
 * @property Carbon $recibido_en
 * @property Carbon|null $entregado_en
 * @property string|null $entregado_a
 * @property int|null $venta_id
 * @property int $garantia_reparacion_dias
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Serie|null $serie
 * @property Producto|null $producto
 * @property Cliente $cliente
 * @property Garantia|null $garantia
 * @property User|null $tecnico
 * @property User $receptor
 * @property Venta|null $venta
 * @property-read Collection<int, OrdenServicioEvento> $eventos
 * @property-read Collection<int, OrdenServicioRepuesto> $repuestos
 * @property-read Collection<int, OrdenServicioFoto> $fotos
 */
class OrdenServicio extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'ordenes_servicio';

    protected $fillable = [
        'codigo',
        'tipo',
        'serie_id',
        'producto_id',
        'equipo_descripcion',
        'serie_externa',
        'cliente_id',
        'garantia_id',
        'falla_reportada',
        'estado_recepcion',
        'estado',
        'tecnico_id',
        'diagnostico',
        'solucion',
        'costo_estimado',
        'costo_final',
        'aprobado_por_cliente',
        'aprobado_en',
        'fecha_promesa',
        'recibido_por',
        'recibido_en',
        'entregado_en',
        'entregado_a',
        'venta_id',
        'garantia_reparacion_dias',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoOrdenServicio::class,
            'estado' => EstadoOrdenServicio::class,
            'costo_estimado' => 'decimal:2',
            'costo_final' => 'decimal:2',
            'aprobado_por_cliente' => 'boolean',
            'aprobado_en' => 'datetime',
            'fecha_promesa' => 'date',
            'recibido_en' => 'datetime',
            'entregado_en' => 'datetime',
            'garantia_reparacion_dias' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Orden de servicio {$this->codigo} ha sido {$eventName}");
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function garantia(): BelongsTo
    {
        return $this->belongsTo(Garantia::class);
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(OrdenServicioEvento::class, 'orden_servicio_id');
    }

    public function repuestos(): HasMany
    {
        return $this->hasMany(OrdenServicioRepuesto::class, 'orden_servicio_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(OrdenServicioFoto::class, 'orden_servicio_id');
    }

    public function esGarantia(): bool
    {
        return $this->tipo === TipoOrdenServicio::GARANTIA;
    }

    public function esPago(): bool
    {
        return $this->tipo === TipoOrdenServicio::PAGO;
    }
}
