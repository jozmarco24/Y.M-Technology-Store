<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoDevolucion: string
{
    case SOLICITADA = 'solicitada';
    case APROBADA = 'aprobada';
    case RECHAZADA = 'rechazada';
    case COMPLETADA = 'completada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SOLICITADA => 'Solicitada',
            self::APROBADA => 'Aprobada',
            self::RECHAZADA => 'Rechazada',
            self::COMPLETADA => 'Completada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SOLICITADA => 'warning',
            self::APROBADA => 'info',
            self::RECHAZADA => 'danger',
            self::COMPLETADA => 'success',
        };
    }
}
