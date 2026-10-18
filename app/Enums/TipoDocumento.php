<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoDocumento: string
{
    case RUC = 'RUC';
    case DNI = 'DNI';
    case CE = 'CE';

    public function etiqueta(): string
    {
        return match ($this) {
            self::RUC => 'RUC',
            self::DNI => 'DNI',
            self::CE => 'Carnet de Extranjería',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RUC => 'primary',
            self::DNI => 'success',
            self::CE => 'warning',
        };
    }
}
