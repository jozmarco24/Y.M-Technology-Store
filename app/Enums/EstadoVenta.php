<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoVenta: string
{
    case COMPLETADA = 'completada';
    case ANULADA = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::COMPLETADA => 'Completada',
            self::ANULADA => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::COMPLETADA => 'success',
            self::ANULADA => 'danger',
        };
    }
}
