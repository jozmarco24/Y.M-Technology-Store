<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoCompra: string
{
    case BORRADOR = 'borrador';
    case RECIBIDA = 'recibida';
    case ANULADA = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::RECIBIDA => 'Recibida',
            self::ANULADA => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BORRADOR => 'gray',
            self::RECIBIDA => 'success',
            self::ANULADA => 'danger',
        };
    }
}
