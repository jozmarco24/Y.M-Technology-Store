<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $titulo
 * @property string|null $subtitulo
 * @property string $imagen_url
 * @property string|null $enlace
 * @property string|null $boton_texto
 * @property int $orden
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Banner extends Model
{
    use HasFactory;

    protected $table = 'banners';

    protected $fillable = [
        'titulo',
        'subtitulo',
        'imagen_url',
        'enlace',
        'boton_texto',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }
}
