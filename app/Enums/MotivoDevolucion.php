<?php

declare(strict_types=1);

namespace App\Enums;

enum MotivoDevolucion: string
{
    case DEFECTO_FABRICA = 'defecto_fabrica';
    case ARREPENTIMIENTO = 'arrepentimiento';
    case ERROR_VENTA = 'error_venta';
    case PRODUCTO_INCORRECTO = 'producto_incorrecto';
    case OTRO = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::DEFECTO_FABRICA => 'Defecto de Fábrica / Falla',
            self::ARREPENTIMIENTO => 'Arrepentimiento del Cliente',
            self::ERROR_VENTA => 'Error en Venta / Digitacion',
            self::PRODUCTO_INCORRECTO => 'Producto Incorrecto Entregado',
            self::OTRO => 'Otro Motivo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DEFECTO_FABRICA => 'danger',
            self::ARREPENTIMIENTO => 'warning',
            self::ERROR_VENTA => 'info',
            self::PRODUCTO_INCORRECTO => 'purple',
            self::OTRO => 'gray',
        };
    }
}
