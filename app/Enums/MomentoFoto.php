<?php

declare(strict_types=1);

namespace App\Enums;

enum MomentoFoto: string
{
    case INGRESO = 'ingreso';
    case DIAGNOSTICO = 'diagnostico';
    case SALIDA = 'salida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO => 'Ingreso / Recepción',
            self::DIAGNOSTICO => 'Diagnóstico',
            self::SALIDA => 'Salida / Entrega',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INGRESO => 'info',
            self::DIAGNOSTICO => 'warning',
            self::SALIDA => 'success',
        };
    }
}
