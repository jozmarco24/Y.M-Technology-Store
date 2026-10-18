<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoComprobante;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $venta_id
 * @property string $tipo
 * @property string $serie
 * @property int $numero
 * @property EstadoComprobante $estado
 * @property array<string, mixed>|null $payload_json
 * @property array<string, mixed>|null $respuesta_json
 * @property string|null $enlace_pdf
 * @property string|null $enlace_xml
 * @property string|null $enlace_cdr
 * @property string|null $codigo_qr
 * @property string|null $hash
 * @property string|null $codigo_sunat
 * @property string|null $mensaje_sunat
 * @property int $intentos
 * @property Carbon|null $ultimo_intento_en
 * @property Carbon|null $aceptado_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Venta|null $venta
 */
class ComprobanteElectronico extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'comprobantes_electronicos';

    protected $fillable = [
        'venta_id',
        'tipo',
        'serie',
        'numero',
        'estado',
        'payload_json',
        'respuesta_json',
        'enlace_pdf',
        'enlace_xml',
        'enlace_cdr',
        'codigo_qr',
        'hash',
        'codigo_sunat',
        'mensaje_sunat',
        'intentos',
        'ultimo_intento_en',
        'aceptado_en',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoComprobante::class,
            'numero' => 'integer',
            'intentos' => 'integer',
            'payload_json' => 'array',
            'respuesta_json' => 'array',
            'ultimo_intento_en' => 'datetime',
            'aceptado_en' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['estado', 'intentos', 'codigo_sunat', 'mensaje_sunat'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function esAceptado(): bool
    {
        return $this->estado === EstadoComprobante::ACEPTADO;
    }

    public function estaAceptado(): bool
    {
        return $this->esAceptado();
    }

    public function esRechazado(): bool
    {
        return $this->estado === EstadoComprobante::RECHAZADO;
    }

    public function esPendiente(): bool
    {
        return $this->estado === EstadoComprobante::PENDIENTE;
    }

    public function esError(): bool
    {
        return $this->estado === EstadoComprobante::ERROR;
    }

    public function esReintentable(int $maxIntentos = 5): bool
    {
        return in_array($this->estado, [EstadoComprobante::PENDIENTE, EstadoComprobante::ERROR], true)
            && $this->intentos < $maxIntentos;
    }

    public function scopePendientesOError(Builder $query): Builder
    {
        return $query->whereIn('estado', [
            EstadoComprobante::PENDIENTE->value,
            EstadoComprobante::ERROR->value,
        ]);
    }

    public function scopeReintentables(Builder $query, int $maxIntentos = 5): Builder
    {
        return $query->whereIn('estado', [
            EstadoComprobante::PENDIENTE->value,
            EstadoComprobante::ERROR->value,
        ])->where('intentos', '<', $maxIntentos);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}
