<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoDevolucion: string
{
    case DEVOLUCION_DINERO = 'devolucion_dinero';
    case CAMBIO_PRODUCTO = 'cambio_producto';
    case NOTA_CREDITO = 'nota_credito';

    public function etiqueta(): string
    {
        return match ($this) {
            self::DEVOLUCION_DINERO => 'Reembolso de Dinero',
            self::CAMBIO_PRODUCTO => 'Cambio de Producto',
            self::NOTA_CREDITO => 'Nota de Crédito',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DEVOLUCION_DINERO => 'warning',
            self::CAMBIO_PRODUCTO => 'info',
            self::NOTA_CREDITO => 'primary',
        };
    }
}
