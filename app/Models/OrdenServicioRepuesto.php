<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $orden_servicio_id
 * @property int|null $producto_id
 * @property string $descripcion
 * @property int $cantidad
 * @property string $costo_unitario
 * @property string $precio_unitario
 * @property int|null $serie_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property OrdenServicio $ordenServicio
 * @property Producto|null $producto
 * @property Serie|null $serie
 */
class OrdenServicioRepuesto extends Model
{
    protected $table = 'orden_servicio_repuestos';

    protected $fillable = [
        'orden_servicio_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'costo_unitario',
        'precio_unitario',
        'serie_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'costo_unitario' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
        ];
    }

    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function subtotalPrecio(): string
    {
        return bcmul((string) $this->precio_unitario, (string) $this->cantidad, 2);
    }

    public function subtotalCosto(): string
    {
        return bcmul((string) $this->costo_unitario, (string) $this->cantidad, 2);
    }
}
