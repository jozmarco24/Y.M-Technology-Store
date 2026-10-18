<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $devolucion_id
 * @property int $venta_id
 * @property string $serie
 * @property int $numero
 * @property string $motivo_sunat
 * @property string $monto
 * @property string $estado
 * @property int|null $comprobante_electronico_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Devolucion|null $devolucion
 * @property Venta|null $venta
 * @property ComprobanteElectronico|null $comprobanteElectronico
 */
class NotaCredito extends Model
{
    use HasFactory;

    protected $table = 'notas_credito';

    protected $fillable = [
        'devolucion_id',
        'venta_id',
        'serie',
        'numero',
        'motivo_sunat',
        'monto',
        'estado',
        'comprobante_electronico_id',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'monto' => 'decimal:2',
        ];
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class, 'devolucion_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function comprobanteElectronico(): BelongsTo
    {
        return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_electronico_id');
    }
}
