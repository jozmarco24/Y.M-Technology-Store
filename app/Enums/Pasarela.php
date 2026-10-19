<?php

declare(strict_types=1);

namespace App\Enums;

enum Pasarela: string
{
    case CULQI = 'culqi';
    case NIUBIZ = 'niubiz';
    case YAPE_MANUAL = 'yape_manual';
    case TRANSFERENCIA = 'transferencia';
    case SIMULADA = 'simulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CULQI => 'Culqi (Tarjetas & Yape)',
            self::NIUBIZ => 'Niubiz / PagoEfectivo',
            self::YAPE_MANUAL => 'Yape / Plin Manual',
            self::TRANSFERENCIA => 'Transferencia Bancaria BCP/BBVA',
            self::SIMULADA => 'Pasarela Simulada (Testing)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CULQI => 'info',
            self::NIUBIZ => 'primary',
            self::YAPE_MANUAL => 'purple',
            self::TRANSFERENCIA => 'gray',
            self::SIMULADA => 'warning',
        };
    }
}
