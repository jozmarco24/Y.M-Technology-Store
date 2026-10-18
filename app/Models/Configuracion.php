<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $clave
 * @property string|null $valor
 * @property string|null $descripcion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Configuracion extends Model
{
    use HasFactory;

    protected $table = 'configuraciones';

    protected $fillable = [
        'clave',
        'valor',
        'descripcion',
    ];

    /**
     * Obtener valor de configuración por clave con valor por defecto.
     */
    public static function obtener(string $clave, ?string $default = null): ?string
    {
        return static::where('clave', $clave)->value('valor') ?? $default;
    }

    /**
     * Guardar o actualizar valor de configuración.
     */
    public static function establecer(string $clave, ?string $valor, ?string $descripcion = null): self
    {
        return static::updateOrCreate(
            ['clave' => $clave],
            array_filter([
                'valor' => $valor,
                'descripcion' => $descripcion,
            ], fn ($v) => $v !== null)
        );
    }
}
