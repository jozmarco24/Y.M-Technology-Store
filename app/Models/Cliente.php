<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoDocumentoCliente;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property TipoDocumentoCliente $tipo_documento
 * @property string|null $numero_documento
 * @property string $nombre_razon_social
 * @property string|null $direccion
 * @property string|null $telefono
 * @property string|null $email
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Cliente extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'nombre_razon_social',
        'direccion',
        'telefono',
        'email',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumentoCliente::class,
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

    public function scopeBuscar(Builder $query, string $termino): Builder
    {
        $term = trim($termino);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('nombre_razon_social', 'ilike', "%{$term}%")
                ->orWhere('numero_documento', 'like', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%");
        });
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }
}
