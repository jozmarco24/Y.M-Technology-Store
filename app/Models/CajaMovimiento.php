<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetodoPago;
use App\Enums\TipoMovimientoCaja;
use BadMethodCallException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $caja_sesion_id
 * @property TipoMovimientoCaja $tipo
 * @property string $concepto
 * @property float $monto
 * @property MetodoPago|null $metodo_pago
 * @property int|null $venta_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CajaMovimiento extends Model
{
    use HasFactory;

    protected $table = 'cajas_movimientos';

    protected $fillable = [
        'caja_sesion_id',
        'tipo',
        'concepto',
        'monto',
        'metodo_pago',
        'venta_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoCaja::class,
            'metodo_pago' => MetodoPago::class,
            'monto' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new BadMethodCallException('Los movimientos de caja son inmutables y no se pueden modificar.');
        });

        static::deleting(function () {
            throw new BadMethodCallException('Los movimientos de caja son inmutables y no se pueden eliminar.');
        });
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class, 'caja_sesion_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
