<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class ClienteWeb extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'clientes_web';

    protected $fillable = [
        'cliente_id',
        'nombre',
        'email',
        'telefono',
        'password',
        'email_verificado_en',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verificado_en' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function direcciones(): HasMany
    {
        return $this->hasMany(DireccionCliente::class, 'cliente_web_id');
    }

    public function direccionPrincipal(): HasOne
    {
        return $this->hasOne(DireccionCliente::class, 'cliente_web_id')->where('es_principal', true);
    }

    public function carritos(): HasMany
    {
        return $this->hasMany(Carrito::class, 'cliente_web_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'cliente_web_id');
    }
}
