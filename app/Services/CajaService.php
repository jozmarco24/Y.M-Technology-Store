<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoCaja;
use App\Enums\MetodoPago;
use App\Enums\TipoMovimientoCaja;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CajaService
{
    /**
     * Abrir una nueva sesión de caja para el usuario.
     * Falla si ya tiene una caja abierta.
     */
    public function abrir(User $usuario, string|float|int $montoApertura, ?string $observaciones = null): CajaSesion
    {
        $montoDec = round((float) $montoApertura, 2);
        if ($montoDec < 0) {
            throw new InvalidArgumentException('El monto de apertura no puede ser negativo.');
        }

        return DB::transaction(function () use ($usuario, $montoDec, $observaciones) {
            $cajaAbierta = CajaSesion::where('user_id', $usuario->id)
                ->where('estado', EstadoCaja::ABIERTA->value)
                ->lockForUpdate()
                ->first();

            if ($cajaAbierta !== null) {
                throw new InvalidArgumentException("El usuario {$usuario->name} ya tiene una sesión de caja abierta (ID: {$cajaAbierta->id}).");
            }

            return CajaSesion::create([
                'user_id' => $usuario->id,
                'abierta_en' => now(),
                'monto_apertura' => $montoDec,
                'estado' => EstadoCaja::ABIERTA,
                'observaciones' => $observaciones,
            ]);
        });
    }

    /**
     * Registrar un movimiento manual o por venta en una sesión de caja.
     * Solo inserción.
     */
    public function registrarMovimiento(
        CajaSesion $cajaSesion,
        TipoMovimientoCaja $tipo,
        string $concepto,
        string|float|int $monto,
        ?MetodoPago $metodoPago = null,
        ?int $ventaId = null,
        ?User $usuario = null
    ): CajaMovimiento {
        $conceptoLimpio = trim($concepto);
        if ($conceptoLimpio === '') {
            throw new InvalidArgumentException('El concepto del movimiento es obligatorio.');
        }

        $montoDec = round((float) $monto, 2);
        if ($montoDec <= 0) {
            throw new InvalidArgumentException('El monto del movimiento debe ser mayor a cero.');
        }

        if (! $cajaSesion->estaAbierta()) {
            throw new InvalidArgumentException("No se pueden registrar movimientos en una caja cerrada (ID: {$cajaSesion->id}).");
        }

        return CajaMovimiento::create([
            'caja_sesion_id' => $cajaSesion->id,
            'tipo' => $tipo,
            'concepto' => $conceptoLimpio,
            'monto' => $montoDec,
            'metodo_pago' => $metodoPago,
            'venta_id' => $ventaId,
            'user_id' => $usuario?->id ?? auth()->id() ?? $cajaSesion->user_id,
        ]);
    }

    /**
     * Cerrar la sesión de caja.
     * Calcula monto esperado:
     *   monto_apertura + ingresos_efectivo - egresos_efectivo
     *   (El dinero físico esperado en el cajón es el efectivo).
     * Calcula diferencia: montoReal - montoEsperado.
     */
    public function cerrar(
        CajaSesion $cajaSesion,
        string|float|int $montoReal,
        User $usuario,
        ?string $observaciones = null
    ): CajaSesion {
        $montoRealDec = round((float) $montoReal, 2);
        if ($montoRealDec < 0) {
            throw new InvalidArgumentException('El monto de cierre real no puede ser negativo.');
        }

        return DB::transaction(function () use ($cajaSesion, $montoRealDec, $observaciones) {
            $sesion = CajaSesion::where('id', $cajaSesion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $sesion->estaAbierta()) {
                throw new InvalidArgumentException('La sesión de caja ya se encuentra cerrada.');
            }

            // Calcular ingresos en efectivo (incluye ventas en efectivo o ingresos manuales en efectivo)
            // Nota: Si metodo_pago es null, se considera movimiento interno de efectivo de caja
            $ingresosEfectivo = (float) CajaMovimiento::where('caja_sesion_id', $sesion->id)
                ->where('tipo', TipoMovimientoCaja::INGRESO->value)
                ->where(function ($q) {
                    $q->where('metodo_pago', MetodoPago::EFECTIVO->value)
                        ->orWhereNull('metodo_pago');
                })
                ->sum('monto');

            $egresosEfectivo = (float) CajaMovimiento::where('caja_sesion_id', $sesion->id)
                ->where('tipo', TipoMovimientoCaja::EGRESO->value)
                ->where(function ($q) {
                    $q->where('metodo_pago', MetodoPago::EFECTIVO->value)
                        ->orWhereNull('metodo_pago');
                })
                ->sum('monto');

            $montoApertura = (float) $sesion->monto_apertura;
            $montoEsperado = round($montoApertura + $ingresosEfectivo - $egresosEfectivo, 2);
            $diferencia = round($montoRealDec - $montoEsperado, 2);

            $sesion->update([
                'cerrada_en' => now(),
                'monto_cierre_esperado' => $montoEsperado,
                'monto_cierre_real' => $montoRealDec,
                'diferencia' => $diferencia,
                'estado' => EstadoCaja::CERRADA,
                'observaciones' => $observaciones ?? $sesion->observaciones,
            ]);

            return $sesion->fresh();
        });
    }

    /**
     * Resumen de totales por método de pago de una sesión de caja.
     *
     * @return array<string, mixed>
     */
    public function resumen(CajaSesion $cajaSesion): array
    {
        $movimientos = CajaMovimiento::where('caja_sesion_id', $cajaSesion->id)->get();

        $porMetodo = [];
        foreach (MetodoPago::cases() as $metodo) {
            $porMetodo[$metodo->value] = [
                'etiqueta' => $metodo->etiqueta(),
                'ingresos' => 0.0,
                'egresos' => 0.0,
                'neto' => 0.0,
            ];
        }

        $totalIngresos = 0.0;
        $totalEgresos = 0.0;

        foreach ($movimientos as $mov) {
            $monto = (float) $mov->monto;
            $metodo = $mov->metodo_pago;
            $metodoKey = $metodo instanceof MetodoPago
                ? $metodo->value
                : ($metodo ? (string) $metodo : MetodoPago::EFECTIVO->value);

            if ($mov->tipo === TipoMovimientoCaja::INGRESO) {
                $totalIngresos += $monto;
                if (isset($porMetodo[$metodoKey])) {
                    $porMetodo[$metodoKey]['ingresos'] += $monto;
                    $porMetodo[$metodoKey]['neto'] += $monto;
                }
            } else {
                $totalEgresos += $monto;
                if (isset($porMetodo[$metodoKey])) {
                    $porMetodo[$metodoKey]['egresos'] += $monto;
                    $porMetodo[$metodoKey]['neto'] -= $monto;
                }
            }
        }

        // Redondear totales
        foreach ($porMetodo as $key => $valores) {
            $porMetodo[$key]['ingresos'] = round($valores['ingresos'], 2);
            $porMetodo[$key]['egresos'] = round($valores['egresos'], 2);
            $porMetodo[$key]['neto'] = round($valores['neto'], 2);
        }

        return [
            'sesion_id' => $cajaSesion->id,
            'usuario' => $cajaSesion->usuario->name,
            'estado' => $cajaSesion->estado->value,
            'monto_apertura' => (float) $cajaSesion->monto_apertura,
            'total_ingresos' => round($totalIngresos, 2),
            'total_egresos' => round($totalEgresos, 2),
            'monto_cierre_esperado' => $cajaSesion->monto_cierre_esperado !== null ? (float) $cajaSesion->monto_cierre_esperado : null,
            'monto_cierre_real' => $cajaSesion->monto_cierre_real !== null ? (float) $cajaSesion->monto_cierre_real : null,
            'diferencia' => $cajaSesion->diferencia !== null ? (float) $cajaSesion->diferencia : null,
            'por_metodo_pago' => $porMetodo,
        ];
    }

    /**
     * Obtiene la sesión activa actual del usuario si existe.
     */
    public function obtenerSesionActiva(User $usuario): ?CajaSesion
    {
        return CajaSesion::where('user_id', $usuario->id)
            ->where('estado', EstadoCaja::ABIERTA->value)
            ->first();
    }
}
