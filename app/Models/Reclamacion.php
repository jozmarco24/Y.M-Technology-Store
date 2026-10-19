<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $codigo
 * @property string $tipo
 * @property string $consumidor_nombre
 * @property string $consumidor_tipo_documento
 * @property string $consumidor_numero_documento
 * @property string $consumidor_telefono
 * @property string $consumidor_email
 * @property string $consumidor_domicilio
 * @property bool $es_menor_edad
 * @property string|null $apoderado_nombre
 * @property string|null $apoderado_telefono
 * @property string|null $apoderado_email
 * @property string $bien_contratado
 * @property float|null $monto_reclamado
 * @property string|null $descripcion_bien
 * @property string $detalle_reclamacion
 * @property string $pedido_consumidor
 * @property string $estado
 * @property string|null $respuesta_proveedor
 * @property Carbon|null $respondido_en
 * @property int|null $respondido_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User|null $usuarioRespuesta
 */
class Reclamacion extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'reclamaciones';

    protected $fillable = [
        'codigo',
        'tipo',
        'consumidor_nombre',
        'consumidor_tipo_documento',
        'consumidor_numero_documento',
        'consumidor_telefono',
        'consumidor_email',
        'consumidor_domicilio',
        'es_menor_edad',
        'apoderado_nombre',
        'apoderado_telefono',
        'apoderado_email',
        'bien_contratado',
        'monto_reclamado',
        'descripcion_bien',
        'detalle_reclamacion',
        'pedido_consumidor',
        'estado',
        'respuesta_proveedor',
        'respondido_en',
        'respondido_por',
    ];

    protected function casts(): array
    {
        return [
            'es_menor_edad' => 'boolean',
            'monto_reclamado' => 'decimal:2',
            'respondido_en' => 'datetime',
        ];
    }

    public function usuarioRespuesta(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondido_por');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['estado', 'respuesta_proveedor', 'respondido_en'])
            ->logOnlyDirty()
            ->useLogName('reclamaciones');
    }

    /**
     * Devuelve true si la reclamación está cerca de vencer (15 días hábiles según INDECOPI).
     */
    public function diasRestantesParaVencer(): int
    {
        if ($this->estado === 'atendido' || $this->estado === 'rechazado') {
            return 0;
        }

        $fechaLimite = $this->created_at ? $this->created_at->copy()->addWeekdays(15) : Carbon::now()->addWeekdays(15);

        return (int) Carbon::now()->diffInWeekdays($fechaLimite, false);
    }
}
