<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoComprobante: string
{
    case PENDIENTE = 'pendiente';
    case ENVIANDO = 'enviando';
    case ACEPTADO = 'aceptado';
    case RECHAZADO = 'rechazado';
    case ERROR = 'error';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::ENVIANDO => 'Enviando',
            self::ACEPTADO => 'Aceptado',
            self::RECHAZADO => 'Rechazado',
            self::ERROR => 'Error',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDIENTE => 'warning',
            self::ENVIANDO => 'info',
            self::ACEPTADO => 'success',
            self::RECHAZADO => 'danger',
            self::ERROR => 'danger',
        };
    }
}
