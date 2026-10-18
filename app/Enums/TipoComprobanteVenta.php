<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoComprobanteVenta: string
{
    case BOLETA = 'boleta';
    case FACTURA = 'factura';
    case NOTA_VENTA = 'nota_venta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BOLETA => 'Boleta de Venta',
            self::FACTURA => 'Factura',
            self::NOTA_VENTA => 'Nota de Venta',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BOLETA => 'info',
            self::FACTURA => 'primary',
            self::NOTA_VENTA => 'warning',
        };
    }
}
