<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoComprobante: string
{
    case FACTURA = 'factura';
    case BOLETA = 'boleta';
    case GUIA = 'guia';
    case OTRO = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::FACTURA => 'Factura',
            self::BOLETA => 'Boleta',
            self::GUIA => 'Guía de Remisión',
            self::OTRO => 'Otro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FACTURA => 'primary',
            self::BOLETA => 'info',
            self::GUIA => 'warning',
            self::OTRO => 'gray',
        };
    }
}
