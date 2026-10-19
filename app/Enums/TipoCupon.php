<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoCupon: string
{
    case PORCENTAJE = 'porcentaje';
    case MONTO = 'monto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PORCENTAJE => 'Porcentaje (%)',
            self::MONTO => 'Monto Fijo (S/)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PORCENTAJE => 'info',
            self::MONTO => 'success',
        };
    }
}
