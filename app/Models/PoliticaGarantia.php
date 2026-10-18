<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $nombre
 * @property int|null $categoria_id
 * @property int|null $producto_id
 * @property int $meses_garantia
 * @property int $dias_cambio
 * @property string|null $cubre
 * @property string|null $no_cubre
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Categoria|null $categoria
 * @property Producto|null $producto
 */
class PoliticaGarantia extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'politicas_garantia';

    protected $fillable = [
        'nombre',
        'categoria_id',
        'producto_id',
        'meses_garantia',
        'dias_cambio',
        'cubre',
        'no_cubre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'meses_garantia' => 'integer',
            'dias_cambio' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nombre', 'categoria_id', 'producto_id', 'meses_garantia', 'dias_cambio', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function garantias(): HasMany
    {
        return $this->hasMany(Garantia::class, 'politica_garantia_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function esGeneral(): bool
    {
        return $this->categoria_id === null && $this->producto_id === null;
    }

    public function snapshotCondiciones(): string
    {
        $salida = "POLÍTICA: {$this->nombre}\n";
        $salida .= "GARANTÍA: {$this->meses_garantia} meses.\n";
        $salida .= "PLAZO DE CAMBIO: {$this->dias_cambio} días calendario.\n";
        if ($this->cubre) {
            $salida .= "CUBRE:\n{$this->cubre}\n";
        }
        if ($this->no_cubre) {
            $salida .= "NO CUBRE:\n{$this->no_cubre}\n";
        }

        return trim($salida);
    }
}
