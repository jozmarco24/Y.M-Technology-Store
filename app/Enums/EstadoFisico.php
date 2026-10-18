<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoFisico: string
{
    case NUEVO_SELLADO = 'nuevo_sellado';
    case ABIERTO_OK = 'abierto_ok';
    case DANADO = 'danado';
    case CON_DEFECTO = 'con_defecto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NUEVO_SELLADO => 'Nuevo / Sellado',
            self::ABIERTO_OK => 'Abierto (Operativo)',
            self::DANADO => 'Dañado / Golpeado',
            self::CON_DEFECTO => 'Con Defecto de Fábrica',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NUEVO_SELLADO => 'success',
            self::ABIERTO_OK => 'info',
            self::DANADO => 'danger',
            self::CON_DEFECTO => 'warning',
        };
    }
}
