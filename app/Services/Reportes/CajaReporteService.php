<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CajaReporteService extends BaseReporteService
{
    /**
     * Diferencias de cierre de caja por vendedor y periodo, con tendencia.
     *
     * @return array<int, array<string, mixed>>
     */
    public function diferenciasCierre(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
        ];

        return $this->recordar('caja_diferencias_cierre', $usuario, $params, function () use ($usuario, $desde, $hasta) {
            $query = DB::table('cajas_sesiones')
                ->join('users', 'users.id', '=', 'cajas_sesiones.user_id')
                ->where('cajas_sesiones.estado', 'cerrada')
                ->when($desde, fn ($q) => $q->whereDate('cajas_sesiones.cerrada_en', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('cajas_sesiones.cerrada_en', '<=', $hasta->toDateString()));

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('cajas_sesiones.user_id', $usuario->id);
            }

            $filas = $query->select([
                'cajas_sesiones.id as sesion_id',
                'users.id as vendedor_id',
                'users.name as vendedor_nombre',
                'cajas_sesiones.abierta_en',
                'cajas_sesiones.cerrada_en',
                'cajas_sesiones.monto_apertura',
                'cajas_sesiones.monto_cierre_esperado',
                'cajas_sesiones.monto_cierre_real',
                'cajas_sesiones.diferencia',
            ])
                ->orderBy('cajas_sesiones.cerrada_en', 'desc')
                ->get();

            return $filas->map(function ($row) {
                $dif = (float) ($row->diferencia ?? 0.0);
                $tipo = 'cuadrada';
                if ($dif > 0) {
                    $tipo = 'sobrante';
                } elseif ($dif < 0) {
                    $tipo = 'faltante';
                }

                return [
                    'sesion_id' => (int) $row->sesion_id,
                    'vendedor_id' => (int) $row->vendedor_id,
                    'vendedor' => (string) $row->vendedor_nombre,
                    'abierta_en' => (string) $row->abierta_en,
                    'cerrada_en' => (string) $row->cerrada_en,
                    'monto_apertura' => round((float) $row->monto_apertura, 2),
                    'monto_esperado' => round((float) ($row->monto_cierre_esperado ?? 0.0), 2),
                    'monto_real' => round((float) ($row->monto_cierre_real ?? 0.0), 2),
                    'diferencia' => round($dif, 2),
                    'tipo_diferencia' => $tipo,
                ];
            })->toArray();
        });
    }

    /**
     * Ingresos y egresos manuales de caja agrupados por concepto.
     *
     * @return array{ingresos: array<int, array<string, mixed>>, egresos: array<int, array<string, mixed>>, balance_neto: float}
     */
    public function movimientosPorConcepto(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
        ];

        return $this->recordar('caja_movimientos_concepto', $usuario, $params, function () use ($usuario, $desde, $hasta) {
            $query = DB::table('cajas_movimientos')
                ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta->toDateString()));

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('user_id', $usuario->id);
            }

            $filas = $query->select([
                'tipo',
                'concepto',
                DB::raw('COUNT(id) as total_operaciones'),
                DB::raw('SUM(monto) as monto_acumulado'),
            ])
                ->groupBy('tipo', 'concepto')
                ->orderByDesc('monto_acumulado')
                ->get();

            $ingresos = [];
            $egresos = [];
            $totalIngresos = 0.0;
            $totalEgresos = 0.0;

            foreach ($filas as $r) {
                $monto = (float) $r->monto_acumulado;
                $item = [
                    'concepto' => (string) $r->concepto,
                    'total_operaciones' => (int) $r->total_operaciones,
                    'monto_acumulado' => round($monto, 2),
                ];

                if ($r->tipo === 'ingreso') {
                    $ingresos[] = $item;
                    $totalIngresos += $monto;
                } else {
                    $egresos[] = $item;
                    $totalEgresos += $monto;
                }
            }

            return [
                'ingresos' => $ingresos,
                'egresos' => $egresos,
                'total_ingresos' => round($totalIngresos, 2),
                'total_egresos' => round($totalEgresos, 2),
                'balance_neto' => round($totalIngresos - $totalEgresos, 2),
            ];
        });
    }

    /**
     * Anulaciones de ventas por usuario y motivos registrados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function anulacionesPorUsuario(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
        ];

        return $this->recordar('caja_anulaciones_usuario', $usuario, $params, function () use ($usuario, $desde, $hasta) {
            $query = DB::table('ventas')
                ->join('users', 'users.id', '=', 'ventas.anulada_por')
                ->where('ventas.estado', 'anulada')
                ->when($desde, fn ($q) => $q->whereDate('ventas.anulada_en', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('ventas.anulada_en', '<=', $hasta->toDateString()));

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('ventas.anulada_por', $usuario->id);
            }

            $filas = $query->select([
                'users.id as user_id',
                'users.name as user_nombre',
                DB::raw('COUNT(ventas.id) as total_anulaciones'),
                DB::raw('SUM(ventas.total) as total_monto_anulado'),
            ])
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total_anulaciones')
                ->get();

            return $filas->map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'usuario' => (string) $r->user_nombre,
                'total_anulaciones' => (int) $r->total_anulaciones,
                'total_monto_anulado' => round((float) $r->total_monto_anulado, 2),
            ])->toArray();
        });
    }
}
