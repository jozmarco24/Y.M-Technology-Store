<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoFisico;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $devolucion_id
 * @property int $venta_detalle_id
 * @property int|null $serie_id
 * @property int $producto_id
 * @property int $cantidad
 * @property string $monto_devuelto
 * @property EstadoFisico $estado_fisico
 * @property string $destino_serie
 * @property Devolucion|null $devolucion
 * @property VentaDetalle|null $ventaDetalle
 * @property Serie|null $serie
 * @property Producto|null $producto
 */
class DevolucionDetalle extends Model
{
    use HasFactory;

    protected $table = 'devolucion_detalles';

    protected $fillable = [
        'devolucion_id',
        'venta_detalle_id',
        'serie_id',
        'producto_id',
        'cantidad',
        'monto_devuelto',
        'estado_fisico',
        'destino_serie',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'monto_devuelto' => 'decimal:2',
            'estado_fisico' => EstadoFisico::class,
        ];
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class, 'devolucion_id');
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class, 'venta_detalle_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'serie_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
