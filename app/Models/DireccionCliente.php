<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DireccionCliente extends Model
{
    use HasFactory;

    protected $table = 'direcciones_cliente';

    protected $fillable = [
        'cliente_web_id',
        'alias',
        'destinatario',
        'telefono',
        'departamento',
        'provincia',
        'distrito',
        'direccion',
        'referencia',
        'es_principal',
    ];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
        ];
    }

    public function clienteWeb(): BelongsTo
    {
        return $this->belongsTo(ClienteWeb::class, 'cliente_web_id');
    }
}
