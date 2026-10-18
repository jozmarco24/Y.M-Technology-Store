<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $devolucion_id
 * @property int $serie_original_id
 * @property int $serie_nueva_id
 * @property string $diferencia_monto
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Devolucion|null $devolucion
 * @property Serie|null $serieOriginal
 * @property Serie|null $serieNueva
 */
class CambioSerie extends Model
{
    use HasFactory;

    protected $table = 'cambios_serie';

    protected $fillable = [
        'devolucion_id',
        'serie_original_id',
        'serie_nueva_id',
        'diferencia_monto',
    ];

    protected function casts(): array
    {
        return [
            'diferencia_monto' => 'decimal:2',
        ];
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class, 'devolucion_id');
    }

    public function serieOriginal(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'serie_original_id');
    }

    public function serieNueva(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'serie_nueva_id');
    }
}
