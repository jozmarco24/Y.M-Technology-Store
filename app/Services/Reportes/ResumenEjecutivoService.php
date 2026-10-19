<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ResumenEjecutivoService extends BaseReporteService
{
    public function __construct(
        protected VentasReporteService $ventasService,
        protected RentabilidadService $rentabilidadService,
        protected InventarioReporteService $inventarioService,
        protected PostventaReporteService $postventaService,
        protected CajaReporteService $cajaService,
    ) {}

    /**
     * Construir resumen ejecutivo para periodo (semanal o mensual).
     *
     * @return array<string, mixed>
     */
    public function generar(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $fechaFin = $hasta ?? Carbon::now();
        $fechaInicio = $desde ?? (clone $fechaFin)->subDays(7);
        $diasPeriodo = max(1, $fechaInicio->diffInDays($fechaFin));

        // Periodo anterior comparable
        $fechaFinAnterior = (clone $fechaInicio)->subDay();
        $fechaInicioAnterior = (clone $fechaFinAnterior)->subDays($diasPeriodo);

        $puedeVerMargen = $this->puedeVerMargen($usuario);

        // 1. Métricas de Ventas actuales y anteriores
        $metricasActual = $this->ventasService->metricasGenerales($usuario, $fechaInicio, $fechaFin);
        $metricasAnterior = $this->ventasService->metricasGenerales($usuario, $fechaInicioAnterior, $fechaFinAnterior);

        $ventasActualMonto = (float) $metricasActual['venta_neta'];
        $ventasAnteriorMonto = (float) $metricasAnterior['venta_neta'];
        $variacionVentas = $ventasAnteriorMonto > 0
            ? round((($ventasActualMonto - $ventasAnteriorMonto) / $ventasAnteriorMonto) * 100, 2)
            : ($ventasActualMonto > 0 ? 100.0 : 0.0);

        // 2. Margen actual y anterior (si tiene permiso)
        $margenActual = 0.0;
        $margenAnterior = 0.0;
        $variacionMargen = 0.0;

        if ($puedeVerMargen) {
            $prodsActual = $this->rentabilidadService->margenPorProducto($usuario, $fechaInicio, $fechaFin, null, 1000);
            $margenActual = round((float) collect($prodsActual)->sum('margen_monto'), 2);

            $prodsAnterior = $this->rentabilidadService->margenPorProducto($usuario, $fechaInicioAnterior, $fechaFinAnterior, null, 1000);
            $margenAnterior = round((float) collect($prodsAnterior)->sum('margen_monto'), 2);

            $variacionMargen = $margenAnterior > 0
                ? round((($margenActual - $margenAnterior) / $margenAnterior) * 100, 2)
                : ($margenActual > 0 ? 100.0 : 0.0);
        }

        // 3. Top productos
        $topProductos = $this->rentabilidadService->margenPorProducto($usuario, $fechaInicio, $fechaFin, null, 5);

        // 4. Inventario crítico
        $stockBajoCount = DB::table('productos')
            ->where('activo', true)
            ->whereRaw('stock_disponible <= stock_minimo')
            ->count();

        $stockMuertoList = $this->inventarioService->stockMuerto($usuario);
        $stockMuertoCount = count($stockMuertoList);

        // 5. Postventa y Calidad
        $devolucionesData = $this->postventaService->devolucionesAnalisis($usuario, $fechaInicio, $fechaFin);
        $garantiasPorVencer = $this->postventaService->garantiasPorVencer($usuario, 30);

        // 6. Comprobantes electrónicos con error o rechazados en el periodo
        $comprobantesProblemas = DB::table('comprobantes_electronicos')
            ->whereIn('estado', ['error', 'rechazado'])
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->count();

        // 7. Diferencias de caja
        $diferenciasCaja = $this->cajaService->diferenciasCierre($usuario, $fechaInicio, $fechaFin);
        $totalDiferenciaCaja = round((float) collect($diferenciasCaja)->sum('diferencia'), 2);

        // 8. Generación de Alertas Ejecutivas en Lenguaje Natural
        $alertas = [];

        if ($variacionVentas < -5.0) {
            $alertas[] = "Las ventas netas cayeron un {$variacionVentas}% en comparación con el periodo anterior.";
        } elseif ($variacionVentas > 10.0) {
            $alertas[] = "Excelente crecimiento: las ventas netas aumentaron un {$variacionVentas}%.";
        }

        if ($puedeVerMargen && $variacionMargen < -5.0) {
            $alertas[] = "El margen bruto cayó un {$variacionMargen}% vs periodo anterior.";
        }

        if ($stockBajoCount > 0) {
            $alertas[] = "Hay {$stockBajoCount} productos con stock crítico en o por debajo del mínimo.";
        }

        if ($stockMuertoCount > 0) {
            $alertas[] = "Se identificaron {$stockMuertoCount} productos catalogados como stock muerto.";
        }

        if ($comprobantesProblemas > 0) {
            $alertas[] = "Atención: existen {$comprobantesProblemas} comprobantes electrónicos con error o rechazo SUNAT.";
        }

        if (abs($totalDiferenciaCaja) > 10.0) {
            $alertaTipo = $totalDiferenciaCaja < 0 ? 'faltante' : 'sobrante';
            $alertas[] = "Se registró una diferencia acumulada de caja de S/ {$totalDiferenciaCaja} ({$alertaTipo}).";
        }

        $resumen = [
            'periodo' => [
                'desde' => $fechaInicio->format('d/m/Y'),
                'hasta' => $fechaFin->format('d/m/Y'),
                'dias' => $diasPeriodo,
            ],
            'ventas' => [
                'total_pedidos' => $metricasActual['total_pedidos'],
                'venta_neta' => $ventasActualMonto,
                'ticket_promedio' => $metricasActual['ticket_promedio'],
                'unidades_por_venta' => $metricasActual['unidades_por_venta'],
                'venta_neta_anterior' => $ventasAnteriorMonto,
                'variacion_ventas_porcentaje' => $variacionVentas,
            ],
            'top_productos' => $topProductos,
            'inventario' => [
                'productos_stock_critico' => $stockBajoCount,
                'productos_stock_muerto' => $stockMuertoCount,
            ],
            'postventa' => [
                'total_devuelto' => $devolucionesData['total_monto_devuelto'],
                'tasa_devolucion_porcentaje' => $devolucionesData['tasa_devolucion_porcentaje'],
                'garantias_por_vencer' => count($garantiasPorVencer),
            ],
            'operaciones' => [
                'comprobantes_con_problemas' => $comprobantesProblemas,
                'diferencia_caja_acumulada' => $totalDiferenciaCaja,
            ],
            'alertas' => $alertas,
        ];

        if ($puedeVerMargen) {
            $resumen['rentabilidad'] = [
                'margen_bruto' => $margenActual,
                'margen_anterior' => $margenAnterior,
                'variacion_margen_porcentaje' => $variacionMargen,
            ];
        }

        return $resumen;
    }
}
