<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoMovimiento: string
{
    case INGRESO = 'ingreso';
    case SALIDA = 'salida';
    case AJUSTE = 'ajuste';
    case ANULACION = 'anulacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO => 'Ingreso',
            self::SALIDA => 'Salida',
            self::AJUSTE => 'Ajuste',
            self::ANULACION => 'Anulación',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INGRESO => 'success',
            self::SALIDA => 'danger',
            self::AJUSTE => 'warning',
            self::ANULACION => 'gray',
        };
    }
}
