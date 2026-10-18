<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoMovimiento;
use BadMethodCallException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'producto_id',
        'serie_id',
        'tipo',
        'cantidad',
        'stock_resultante',
        'referencia_tipo',
        'referencia_id',
        'motivo',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimiento::class,
            'cantidad' => 'integer',
            'stock_resultante' => 'integer',
            'referencia_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new BadMethodCallException('Los movimientos de inventario son inmutables y no se pueden modificar.');
        });

        static::deleting(function () {
            throw new BadMethodCallException('Los movimientos de inventario son inmutables y no se pueden eliminar.');
        });
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'serie_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
