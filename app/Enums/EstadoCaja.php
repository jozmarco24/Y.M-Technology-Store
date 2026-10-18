<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoCaja: string
{
    case ABIERTA = 'abierta';
    case CERRADA = 'cerrada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ABIERTA => 'Abierta',
            self::CERRADA => 'Cerrada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ABIERTA => 'success',
            self::CERRADA => 'gray',
        };
    }
}
