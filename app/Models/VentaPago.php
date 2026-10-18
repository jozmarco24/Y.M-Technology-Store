<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetodoPago;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venta_id
 * @property MetodoPago $metodo
 * @property float $monto
 * @property string|null $referencia
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Venta|null $venta
 */
class VentaPago extends Model
{
    use HasFactory;

    protected $table = 'venta_pagos';

    protected $fillable = [
        'venta_id',
        'metodo',
        'monto',
        'referencia',
    ];

    protected function casts(): array
    {
        return [
            'metodo' => MetodoPago::class,
            'monto' => 'decimal:2',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}
