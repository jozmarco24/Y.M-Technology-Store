<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoOrdenServicio: string
{
    case RECIBIDO = 'recibido';
    case DIAGNOSTICO = 'diagnostico';
    case ESPERANDO_APROBACION = 'esperando_aprobacion';
    case ESPERANDO_REPUESTO = 'esperando_repuesto';
    case EN_REPARACION = 'en_reparacion';
    case REPARADO = 'reparado';
    case ENTREGADO = 'entregado';
    case CANCELADO = 'cancelado';
    case SIN_SOLUCION = 'sin_solucion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::RECIBIDO => 'Recibido',
            self::DIAGNOSTICO => 'En Diagnóstico',
            self::ESPERANDO_APROBACION => 'Esperando Aprobación',
            self::ESPERANDO_REPUESTO => 'Esperando Repuesto',
            self::EN_REPARACION => 'En Reparación',
            self::REPARADO => 'Reparado',
            self::ENTREGADO => 'Entregado',
            self::CANCELADO => 'Cancelado',
            self::SIN_SOLUCION => 'Sin Solución',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RECIBIDO => 'gray',
            self::DIAGNOSTICO => 'info',
            self::ESPERANDO_APROBACION => 'warning',
            self::ESPERANDO_REPUESTO => 'warning',
            self::EN_REPARACION => 'primary',
            self::REPARADO => 'success',
            self::ENTREGADO => 'success',
            self::CANCELADO => 'danger',
            self::SIN_SOLUCION => 'danger',
        };
    }

    /**
     * Valida si la transición desde el estado actual hacia el nuevo estado es válida.
     */
    public function puedePasarA(self $nuevoEstado): bool
    {
        if ($this === $nuevoEstado) {
            return false;
        }

        return match ($this) {
            self::RECIBIDO => in_array($nuevoEstado, [
                self::DIAGNOSTICO,
                self::CANCELADO,
            ], true),

            self::DIAGNOSTICO => in_array($nuevoEstado, [
                self::ESPERANDO_APROBACION,
                self::ESPERANDO_REPUESTO,
                self::EN_REPARACION,
                self::REPARADO,
                self::SIN_SOLUCION,
                self::CANCELADO,
            ], true),

            self::ESPERANDO_APROBACION => in_array($nuevoEstado, [
                self::ESPERANDO_REPUESTO,
                self::EN_REPARACION,
                self::CANCELADO,
            ], true),

            self::ESPERANDO_REPUESTO => in_array($nuevoEstado, [
                self::EN_REPARACION,
                self::SIN_SOLUCION,
                self::CANCELADO,
            ], true),

            self::EN_REPARACION => in_array($nuevoEstado, [
                self::ESPERANDO_REPUESTO,
                self::REPARADO,
                self::SIN_SOLUCION,
                self::CANCELADO,
            ], true),

            self::REPARADO => in_array($nuevoEstado, [
                self::ENTREGADO,
            ], true),

            self::SIN_SOLUCION => in_array($nuevoEstado, [
                self::ENTREGADO,
                self::CANCELADO,
            ], true),

            self::CANCELADO => in_array($nuevoEstado, [
                self::ENTREGADO,
            ], true),

            self::ENTREGADO => false,
        };
    }
}
