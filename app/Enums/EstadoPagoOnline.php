<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoPagoOnline: string
{
    case PENDIENTE = 'pendiente';
    case AUTORIZADO = 'autorizado';
    case PAGADO = 'pagado';
    case FALLIDO = 'fallido';
    case REEMBOLSADO = 'reembolsado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::AUTORIZADO => 'Autorizado',
            self::PAGADO => 'Pagado',
            self::FALLIDO => 'Fallido',
            self::REEMBOLSADO => 'Reembolsado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDIENTE => 'warning',
            self::AUTORIZADO => 'info',
            self::PAGADO => 'success',
            self::FALLIDO => 'danger',
            self::REEMBOLSADO => 'gray',
        };
    }
}
