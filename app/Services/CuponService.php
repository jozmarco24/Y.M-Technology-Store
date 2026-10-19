<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cupon;

class CuponService
{
    /**
     * Validar si un cupón es aplicable a un subtotal determinado.
     *
     * @return array{
     *     valido: bool,
     *     cupon: Cupon|null,
     *     descuento: float,
     *     mensaje: string
     * }
     */
    public function validar(string $codigo, float $subtotal): array
    {
        $codigoLimpio = strtoupper(trim($codigo));
        $cupon = Cupon::where('codigo', $codigoLimpio)->first();

        if (! $cupon) {
            return [
                'valido' => false,
                'cupon' => null,
                'descuento' => 0.0,
                'mensaje' => 'El código de cupón no existe.',
            ];
        }

        if (! $cupon->activo) {
            return [
                'valido' => false,
                'cupon' => null,
                'descuento' => 0.0,
                'mensaje' => 'El cupón no se encuentra activo.',
            ];
        }

        if (! $cupon->esVigente()) {
            return [
                'valido' => false,
                'cupon' => null,
                'descuento' => 0.0,
                'mensaje' => 'El cupón ha vencido o alcanzó el límite de usos permitidos.',
            ];
        }

        if ($cupon->minimo_compra !== null && $subtotal < (float) $cupon->minimo_compra) {
            return [
                'valido' => false,
                'cupon' => null,
                'descuento' => 0.0,
                'mensaje' => "El monto mínimo de compra para este cupón es S/ {$cupon->minimo_compra}.",
            ];
        }

        $descuento = $cupon->calcularDescuento($subtotal);

        return [
            'valido' => true,
            'cupon' => $cupon,
            'descuento' => $descuento,
            'mensaje' => "Cupón aplicado correctamente: S/ {$descuento} de descuento.",
        ];
    }

    /**
     * Incrementar el contador de uso del cupón tras confirmarse el pago.
     */
    public function registrarUso(Cupon $cupon): void
    {
        $cupon->increment('usos_actuales');
    }
}
