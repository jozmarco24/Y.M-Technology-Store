<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoSerie: string
{
    case DISPONIBLE = 'disponible';
    case RESERVADA = 'reservada';
    case VENDIDA = 'vendida';
    case EN_GARANTIA = 'en_garantia';
    case DEVUELTA = 'devuelta';
    case BAJA = 'baja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::DISPONIBLE => 'Disponible',
            self::RESERVADA => 'Reservada',
            self::VENDIDA => 'Vendida',
            self::EN_GARANTIA => 'En Garantía',
            self::DEVUELTA => 'Devuelta',
            self::BAJA => 'Baja',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DISPONIBLE => 'success',
            self::RESERVADA => 'warning',
            self::VENDIDA => 'info',
            self::EN_GARANTIA => 'purple',
            self::DEVUELTA => 'gray',
            self::BAJA => 'danger',
        };
    }

    public function puedePasarA(self $destino, bool $esAnulacionVenta = false): bool
    {
        if ($this === $destino) {
            return false;
        }

        return match ($this) {
            self::DISPONIBLE => in_array($destino, [self::RESERVADA, self::VENDIDA, self::BAJA], true),
            self::RESERVADA => in_array($destino, [self::DISPONIBLE, self::VENDIDA, self::BAJA], true),
            self::VENDIDA => $esAnulacionVenta
                ? in_array($destino, [self::DISPONIBLE, self::EN_GARANTIA, self::DEVUELTA], true)
                : in_array($destino, [self::EN_GARANTIA, self::DEVUELTA], true),
            self::EN_GARANTIA => in_array($destino, [self::DISPONIBLE, self::DEVUELTA, self::BAJA], true),
            self::DEVUELTA => in_array($destino, [self::DISPONIBLE, self::BAJA], true),
            self::BAJA => false,
        };
    }
}
