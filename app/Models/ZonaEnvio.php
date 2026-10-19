<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZonaEnvio extends Model
{
    use HasFactory;

    protected $table = 'zonas_envio';

    protected $fillable = [
        'nombre',
        'departamento',
        'provincia',
        'distrito',
        'costo',
        'dias_estimados',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'costo' => 'decimal:2',
            'dias_estimados' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
