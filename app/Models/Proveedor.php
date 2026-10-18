<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Proveedor extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'razon_social',
        'contacto',
        'telefono',
        'email',
        'direccion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }
}
