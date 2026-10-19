<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItemSerie extends Model
{
    use HasFactory;

    protected $table = 'pedido_item_series';

    protected $fillable = [
        'pedido_item_id',
        'serie_id',
    ];

    public function pedidoItem(): BelongsTo
    {
        return $this->belongsTo(PedidoItem::class, 'pedido_item_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'serie_id');
    }
}
