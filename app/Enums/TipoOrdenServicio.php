<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoOrdenServicio: string
{
    case GARANTIA = 'garantia';
    case PAGO = 'pago';

    public function etiqueta(): string
    {
        return match ($this) {
            self::GARANTIA => 'Garantía',
            self::PAGO => 'Con Costo (Pago)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::GARANTIA => 'success',
            self::PAGO => 'info',
        };
    }
}
