<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoMovimientoCaja: string
{
    case INGRESO = 'ingreso';
    case EGRESO = 'egreso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO => 'Ingreso',
            self::EGRESO => 'Egreso',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INGRESO => 'success',
            self::EGRESO => 'danger',
        };
    }
}
