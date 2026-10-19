<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookRecibido extends Model
{
    use HasFactory;

    protected $table = 'webhooks_recibidos';

    protected $fillable = [
        'pasarela',
        'evento_id',
        'payload',
        'procesado',
        'procesado_en',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'procesado' => 'boolean',
            'procesado_en' => 'datetime',
        ];
    }
}
