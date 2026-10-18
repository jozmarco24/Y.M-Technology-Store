<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoGarantia: string
{
    case VIGENTE = 'vigente';
    case VENCIDA = 'vencida';
    case ANULADA = 'anulada';
    case EN_USO = 'en_uso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VIGENTE => 'Vigente',
            self::VENCIDA => 'Vencida',
            self::ANULADA => 'Anulada',
            self::EN_USO => 'En Uso (RMA / Reparación)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::VIGENTE => 'success',
            self::VENCIDA => 'gray',
            self::ANULADA => 'danger',
            self::EN_USO => 'warning',
        };
    }
}
