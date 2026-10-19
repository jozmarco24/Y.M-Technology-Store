<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrito extends Model
{
    use HasFactory;

    protected $table = 'carritos';

    protected $fillable = [
        'cliente_web_id',
        'token',
        'expira_en',
    ];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
        ];
    }

    public function clienteWeb(): BelongsTo
    {
        return $this->belongsTo(ClienteWeb::class, 'cliente_web_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CarritoItem::class, 'carrito_id');
    }
}
