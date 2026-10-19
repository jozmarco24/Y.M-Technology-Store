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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

    /**
     * Anonimiza los datos personales del cliente (derecho al olvido / cancelación),
     * preservando la integridad referencial y comprobantes tributarios exigidos por SUNAT.
     */
    public function anonimizarDatos(): void
    {
        $idAnonimo = $this->id;

        $this->update([
            'nombre' => "Usuario Anonimizado #{$idAnonimo}",
            'email' => "anonimo_{$idAnonimo}@eliminado.local",
            'telefono' => null,
            'password' => Hash::make(Str::random(32)),
            'activo' => false,
        ]);

        // Anonimizar o borrar direcciones guardadas
        $this->direcciones()->delete();
        $this->delete(); // Soft delete
    }
}
