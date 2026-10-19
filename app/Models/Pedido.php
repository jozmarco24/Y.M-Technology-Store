<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoPedido;
use App\Enums\MetodoEntrega;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int|null $cliente_web_id
 * @property int|null $cliente_id
 * @property EstadoPedido $estado
 * @property MetodoEntrega $metodo_entrega
 * @property array|null $direccion_snapshot
 * @property int|null $zona_envio_id
 * @property int|null $cupon_id
 * @property float $subtotal
 * @property float $costo_envio
 * @property float $descuento
 * @property float $total
 * @property string $tipo_comprobante
 * @property string $documento_tipo
 * @property string $documento_numero
 * @property string|null $razon_social
 * @property string|null $direccion_fiscal
 * @property string $email_contacto
 * @property string|null $telefono_contacto
 * @property int|null $venta_id
 * @property Carbon|null $reserva_expira_en
 * @property string|null $cancelado_motivo
 * @property string|null $tracking_codigo
 * @property string|null $courier
 * @property string|null $notas_cliente
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Pedido extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'pedidos';

    protected $fillable = [
        'codigo',
        'cliente_web_id',
        'cliente_id',
        'estado',
        'metodo_entrega',
        'direccion_snapshot',
        'zona_envio_id',
        'cupon_id',
        'subtotal',
        'costo_envio',
        'descuento',
        'total',
        'tipo_comprobante',
        'documento_tipo',
        'documento_numero',
        'razon_social',
        'direccion_fiscal',
        'email_contacto',
        'telefono_contacto',
        'venta_id',
        'reserva_expira_en',
        'cancelado_motivo',
        'tracking_codigo',
        'courier',
        'notas_cliente',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPedido::class,
            'metodo_entrega' => MetodoEntrega::class,
            'direccion_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'costo_envio' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            'reserva_expira_en' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function clienteWeb(): BelongsTo
    {
        return $this->belongsTo(ClienteWeb::class, 'cliente_web_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function zonaEnvio(): BelongsTo
    {
        return $this->belongsTo(ZonaEnvio::class, 'zona_envio_id');
    }

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class, 'cupon_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class, 'pedido_id');
    }

    public function pagosOnline(): HasMany
    {
        return $this->hasMany(PagoOnline::class, 'pedido_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(PedidoEvento::class, 'pedido_id')->orderBy('id', 'asc');
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoPedido::PENDIENTE_PAGO);
    }

    public function scopeReservasVencidas(Builder $query): Builder
    {
        return $query->where('estado', EstadoPedido::PENDIENTE_PAGO)
            ->whereNotNull('reserva_expira_en')
            ->where('reserva_expira_en', '<=', Carbon::now());
    }

    public function estaExpirado(): bool
    {
        if ($this->estado !== EstadoPedido::PENDIENTE_PAGO) {
            return false;
        }

        return $this->reserva_expira_en !== null && Carbon::now()->gte($this->reserva_expira_en);
    }
}
