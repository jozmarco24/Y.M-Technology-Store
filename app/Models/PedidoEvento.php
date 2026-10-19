<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoEvento extends Model
{
    use HasFactory;

    protected $table = 'pedido_eventos';

    protected $fillable = [
        'pedido_id',
        'estado_anterior',
        'estado_nuevo',
        'comentario',
        'user_id',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
