<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoPagoOnline;
use App\Enums\Pasarela;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $pedido_id
 * @property Pasarela $pasarela
 * @property string|null $referencia_externa
 * @property EstadoPagoOnline $estado
 * @property float $monto
 * @property string $moneda
 * @property array|null $respuesta_json
 * @property int $intentos
 * @property Carbon|null $pagado_en
 * @property string $idempotency_key
 */
class PagoOnline extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'pagos_online';

    protected $fillable = [
        'pedido_id',
        'pasarela',
        'referencia_externa',
        'estado',
        'monto',
        'moneda',
        'respuesta_json',
        'intentos',
        'pagado_en',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'pasarela' => Pasarela::class,
            'estado' => EstadoPagoOnline::class,
            'monto' => 'decimal:2',
            'respuesta_json' => 'array',
            'intentos' => 'integer',
            'pagado_en' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }
}
