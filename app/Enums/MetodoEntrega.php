<?php

declare(strict_types=1);

namespace App\Enums;

enum MetodoEntrega: string
{
    case RECOJO_TIENDA = 'recojo_tienda';
    case DELIVERY_LIMA = 'delivery_lima';
    case ENVIO_PROVINCIA = 'envio_provincia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::RECOJO_TIENDA => 'Recojo en Tienda',
            self::DELIVERY_LIMA => 'Delivery Express Lima',
            self::ENVIO_PROVINCIA => 'Envío a Provincia',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RECOJO_TIENDA => 'info',
            self::DELIVERY_LIMA => 'success',
            self::ENVIO_PROVINCIA => 'warning',
        };
    }
}
