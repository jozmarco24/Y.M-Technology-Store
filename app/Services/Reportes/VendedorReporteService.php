<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\Configuracion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VendedorReporteService extends BaseReporteService
{
    /**
     * Ranking de vendedores por ventas, margen, ticket promedio, descuentos y anulaciones.
     * Margen visible únicamente para usuarios con permiso 'reportes.ver_margen'.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ranking(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
        ];

        return $this->recordar('vendedor_ranking', $usuario, $params, function () use ($usuario, $desde, $hasta) {
            $puedeVerMargen = $this->puedeVerMargen($usuario);

            // Ventas completadas por vendedor
            $ventasQuery = DB::table('ventas')
                ->join('users', 'users.id', '=', 'ventas.vendedor_id')
                ->where('ventas.estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $ventasQuery->where('ventas.vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($ventasQuery, $desde, $hasta, null);

            $filasVentas = $ventasQuery->select([
                'users.id as vendedor_id',
                'users.name as vendedor_nombre',
                DB::raw('COUNT(ventas.id) as total_pedidos'),
                DB::raw('SUM(ventas.subtotal) as subtotal_acumulado'),
                DB::raw('SUM(ventas.descuento) as descuentos_acumulados'),
                DB::raw('SUM(ventas.total) as total_vendido'),
            ])
                ->groupBy('users.id', 'users.name')
                ->get()
                ->keyBy('vendedor_id');

            // Margen acumulado por vendedor (si es admin)
            $margenPorVendedor = [];
            if ($puedeVerMargen) {
                $margenQuery = DB::table('venta_detalles')
                    ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                    ->where('ventas.estado', 'completada');

                $this->aplicarFiltrosVentas($margenQuery, $desde, $hasta, null, 'ventas.created_at', 'ventas.canal');

                $filasMargen = $margenQuery->select([
                    'ventas.vendedor_id',
                    DB::raw('SUM(venta_detalles.subtotal - (venta_detalles.cantidad * venta_detalles.costo_unitario)) as margen_monto'),
                ])
                    ->groupBy('ventas.vendedor_id')
                    ->get()
                    ->keyBy('vendedor_id');

                foreach ($filasMargen as $vId => $mRow) {
                    $margenPorVendedor[$vId] = (float) $mRow->margen_monto;
                }
            }

            // Anulaciones por vendedor
            $anulaciones = DB::table('ventas')
                ->where('estado', 'anulada')
                ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta->toDateString()))
                ->select([
                    'vendedor_id',
                    DB::raw('COUNT(id) as total_anuladas'),
                ])
                ->groupBy('vendedor_id')
                ->get()
                ->keyBy('vendedor_id');

            $ranking = [];
            foreach ($filasVentas as $vendedorId => $vRow) {
                $pedidos = (int) $vRow->total_pedidos;
                $totalVendido = (float) $vRow->total_vendido;
                $ticketPromedio = $pedidos > 0 ? round($totalVendido / $pedidos, 2) : 0.0;
                $descuentos = (float) $vRow->descuentos_acumulados;
                $cantAnuladas = (int) ($anulaciones->get($vendedorId)->total_anuladas ?? 0);

                $item = [
                    'vendedor_id' => (int) $vendedorId,
                    'vendedor' => (string) $vRow->vendedor_nombre,
                    'total_pedidos' => $pedidos,
                    'total_vendido' => round($totalVendido, 2),
                    'ticket_promedio' => $ticketPromedio,
                    'descuentos_otorgados' => round($descuentos, 2),
                    'ventas_anuladas' => $cantAnuladas,
                ];

                if ($puedeVerMargen) {
                    $margen = round($margenPorVendedor[$vendedorId] ?? 0.0, 2);
                    $item['margen_total'] = $margen;
                    $item['margen_porcentaje'] = $totalVendido > 0 ? round(($margen / $totalVendido) * 100, 2) : 0.0;
                }

                $ranking[] = $item;
            }

            return collect($ranking)->sortByDesc('total_vendido')->values()->toArray();
        });
    }

    /**
     * Cálculo de comisiones por vendedor (opcional, protegido detrás de flag).
     * Flag de configuración: 'activar_comisiones' (default '0').
     *
     * @return array<int, array<string, mixed>>
     */
    public function calcularComisiones(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        if (! $this->puedeVerMargen($usuario)) {
            return [];
        }

        $comisionesActivas = (bool) (int) Configuracion::obtener('activar_comisiones', '0');
        if (! $comisionesActivas) {
            return [];
        }

        // Obtener reglas activas
        $reglas = DB::table('reglas_comision')->where('activo', true)->get();
        if ($reglas->isEmpty()) {
            return [];
        }

        $ranking = $this->ranking($usuario, $desde, $hasta);
        $reglaGeneral = $reglas->first(); // Regla global predeterminada

        return array_map(function ($row) use ($reglaGeneral) {
            $porcentaje = (float) $reglaGeneral->porcentaje;
            $base = $reglaGeneral->tipo === 'porcentaje_margen'
                ? (float) ($row['margen_total'] ?? 0.0)
                : (float) $row['total_vendido'];

            $comision = round(($base * $porcentaje) / 100, 2);

            return [
                'vendedor_id' => $row['vendedor_id'],
                'vendedor' => $row['vendedor'],
                'base_calculo' => round($base, 2),
                'tipo_comision' => $reglaGeneral->tipo,
                'porcentaje_aplicado' => $porcentaje,
                'monto_comision' => max(0.0, $comision),
            ];
        }, $ranking);
    }
}
