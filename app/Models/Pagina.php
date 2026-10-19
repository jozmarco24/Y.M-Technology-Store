<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $titulo
 * @property string $slug
 * @property string $contenido
 * @property string|null $meta_titulo
 * @property string|null $meta_descripcion
 * @property bool $publicada
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Pagina extends Model
{
    use HasFactory;

    protected $table = 'paginas';

    protected $fillable = [
        'titulo',
        'slug',
        'contenido',
        'meta_titulo',
        'meta_descripcion',
        'publicada',
    ];

    protected function casts(): array
    {
        return [
            'publicada' => 'boolean',
        ];
    }
}
