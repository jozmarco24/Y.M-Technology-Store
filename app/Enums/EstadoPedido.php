<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoPedido: string
{
    case PENDIENTE_PAGO = 'pendiente_pago';
    case PAGADO = 'pagado';
    case PREPARANDO = 'preparando';
    case ENVIADO = 'enviado';
    case ENTREGADO = 'entregado';
    case CANCELADO = 'cancelado';
    case EXPIRADO = 'expirado';
    case REEMBOLSADO = 'reembolsado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE_PAGO => 'Pendiente de Pago',
            self::PAGADO => 'Pagado',
            self::PREPARANDO => 'En Preparación',
            self::ENVIADO => 'Enviado',
            self::ENTREGADO => 'Entregado',
            self::CANCELADO => 'Cancelado',
            self::EXPIRADO => 'Expirado',
            self::REEMBOLSADO => 'Reembolsado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDIENTE_PAGO => 'warning',
            self::PAGADO => 'success',
            self::PREPARANDO => 'info',
            self::ENVIADO => 'purple',
            self::ENTREGADO => 'emerald',
            self::CANCELADO => 'danger',
            self::EXPIRADO => 'gray',
            self::REEMBOLSADO => 'amber',
        };
    }

    public function puedePasarA(self $destino): bool
    {
        if ($this === $destino) {
            return false;
        }

        return match ($this) {
            self::PENDIENTE_PAGO => in_array($destino, [self::PAGADO, self::CANCELADO, self::EXPIRADO], true),
            self::PAGADO => in_array($destino, [self::PREPARANDO, self::ENVIADO, self::CANCELADO, self::REEMBOLSADO], true),
            self::PREPARANDO => in_array($destino, [self::ENVIADO, self::ENTREGADO, self::CANCELADO, self::REEMBOLSADO], true),
            self::ENVIADO => in_array($destino, [self::ENTREGADO, self::REEMBOLSADO], true),
            self::ENTREGADO => in_array($destino, [self::REEMBOLSADO], true),
            self::CANCELADO => false,
            self::EXPIRADO => in_array($destino, [self::PAGADO, self::REEMBOLSADO], true), // Para pago tardío o reactivación excepcional
            self::REEMBOLSADO => false,
        };
    }
}
