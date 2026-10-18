<?php

declare(strict_types=1);

namespace App\Enums;

enum MetodoPago: string
{
    case EFECTIVO = 'efectivo';
    case YAPE = 'yape';
    case PLIN = 'plin';
    case TARJETA = 'tarjeta';
    case TRANSFERENCIA = 'transferencia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EFECTIVO => 'Efectivo',
            self::YAPE => 'Yape',
            self::PLIN => 'Plin',
            self::TARJETA => 'Tarjeta Débito/Crédito',
            self::TRANSFERENCIA => 'Transferencia Bancaria',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EFECTIVO => 'success',
            self::YAPE => 'purple',
            self::PLIN => 'info',
            self::TARJETA => 'warning',
            self::TRANSFERENCIA => 'gray',
        };
    }
}
