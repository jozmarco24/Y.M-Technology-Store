<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PostventaReporteService extends BaseReporteService
{
    /**
     * Tasa de fallas por modelo, marca y proveedor:
     * Tasa de fallas = Órdenes de servicio creadas / Unidades vendidas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tasaFallas(User $usuario): array
    {
        return $this->recordar('postventa_tasa_fallas', $usuario, [], function () {
            // Unidades vendidas por producto
            $ventas = DB::table('venta_detalles')
                ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                ->where('ventas.estado', 'completada')
                ->select([
                    'venta_detalles.producto_id',
                    DB::raw('SUM(venta_detalles.cantidad) as total_vendido'),
                ])
                ->groupBy('venta_detalles.producto_id')
                ->get()
                ->keyBy('producto_id');

            // Órdenes de servicio registradas por producto
            $ordenes = DB::table('ordenes_servicio')
                ->whereNotNull('producto_id')
                ->select([
                    'producto_id',
                    DB::raw('COUNT(id) as total_fallas'),
                ])
                ->groupBy('producto_id')
                ->get()
                ->keyBy('producto_id');

            $productos = DB::table('productos')
                ->leftJoin('marcas', 'marcas.id', '=', 'productos.marca_id')
                ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
                ->select([
                    'productos.id',
                    'productos.nombre',
                    'productos.sku',
                    'marcas.nombre as marca',
                    'categorias.nombre as categoria',
                ])
                ->get();

            return $productos->map(function ($prod) use ($ventas, $ordenes) {
                $vendidos = (int) ($ventas->get($prod->id)->total_vendido ?? 0);
                $fallas = (int) ($ordenes->get($prod->id)->total_fallas ?? 0);

                $tasaFallas = $vendidos > 0 ? round(($fallas / $vendidos) * 100, 2) : 0.0;

                return [
                    'producto_id' => (int) $prod->id,
                    'producto' => (string) $prod->nombre,
                    'sku' => (string) $prod->sku,
                    'marca' => (string) ($prod->marca ?? 'Sin marca'),
                    'categoria' => (string) ($prod->categoria ?? 'Sin categoría'),
                    'unidades_vendidas' => $vendidos,
                    'ordenes_servicio' => $fallas,
                    'tasa_fallas_porcentaje' => $tasaFallas,
                ];
            })->filter(fn ($p) => $p['ordenes_servicio'] > 0 || $p['unidades_vendidas'] > 0)
                ->sortByDesc('tasa_fallas_porcentaje')
                ->values()
                ->toArray();
        });
    }

    /**
     * Tiempos promedio de reparación y órdenes retrasadas.
     *
     * @return array<string, mixed>
     */
    public function tiemposReparacion(User $usuario): array
    {
        return $this->recordar('postventa_tiempos_reparacion', $usuario, [], function () {
            // Órdenes entregadas para tiempo promedio
            $entregadas = DB::table('ordenes_servicio')
                ->whereNotNull('entregado_en')
                ->select([
                    'id',
                    'recibido_en',
                    'entregado_en',
                ])
                ->get();

            $totalDias = 0;
            $cantidadEntregadas = $entregadas->count();

            foreach ($entregadas as $ord) {
                $inicio = Carbon::parse($ord->recibido_en);
                $fin = Carbon::parse($ord->entregado_en);
                $totalDias += max(0, $inicio->diffInDays($fin));
            }

            $promedioDias = $cantidadEntregadas > 0 ? round($totalDias / $cantidadEntregadas, 1) : 0.0;

            // Órdenes retrasadas (en proceso y con fecha_promesa vencida)
            $ahora = Carbon::now();
            $estadosActivos = [
                'recibido', 'diagnostico', 'esperando_aprobacion',
                'esperando_repuesto', 'en_reparacion', 'reparado',
            ];

            $retrasadas = DB::table('ordenes_servicio')
                ->join('clientes', 'clientes.id', '=', 'ordenes_servicio.cliente_id')
                ->whereIn('ordenes_servicio.estado', $estadosActivos)
                ->whereNotNull('ordenes_servicio.fecha_promesa')
                ->where('ordenes_servicio.fecha_promesa', '<', $ahora->toDateString())
                ->select([
                    'ordenes_servicio.id',
                    'ordenes_servicio.codigo',
                    'ordenes_servicio.tipo',
                    'ordenes_servicio.estado',
                    'ordenes_servicio.fecha_promesa',
                    'ordenes_servicio.recibido_en',
                    'clientes.nombre_razon_social as cliente_nombre',
                ])
                ->get()
                ->map(fn ($r) => [
                    'id' => (int) $r->id,
                    'codigo' => (string) $r->codigo,
                    'tipo' => (string) $r->tipo,
                    'estado' => (string) $r->estado,
                    'fecha_promesa' => (string) $r->fecha_promesa,
                    'dias_retraso' => (int) Carbon::parse($r->fecha_promesa)->diffInDays($ahora),
                    'cliente' => (string) $r->cliente_nombre,
                ])
                ->toArray();

            return [
                'tiempo_promedio_dias' => $promedioDias,
                'total_ordenes_entregadas' => $cantidadEntregadas,
                'total_ordenes_retrasadas' => count($retrasadas),
                'ordenes_retrasadas' => $retrasadas,
            ];
        });
    }

    /**
     * Costo de garantías asumido por la tienda por mes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function costoGarantiasMensual(User $usuario, ?int $anio = null): array
    {
        $anioActual = $anio ?? (int) Carbon::now()->year;

        $params = ['anio' => $anioActual];

        return $this->recordar('postventa_costo_garantias', $usuario, $params, function () use ($usuario, $anioActual) {
            $puedeVerCostos = $this->puedeVerMargen($usuario);

            // Órdenes de tipo 'garantia'
            $ordenesQuery = DB::table('ordenes_servicio')
                ->where('tipo', 'garantia')
                ->whereYear('recibido_en', $anioActual);

            $selects = [
                DB::raw('EXTRACT(MONTH FROM recibido_en) as mes'),
                DB::raw('COUNT(id) as total_atenciones'),
            ];

            if ($puedeVerCostos) {
                $selects[] = DB::raw('SUM(COALESCE(costo_final, 0)) as costo_asumido');
            }

            $filas = $ordenesQuery->select($selects)
                ->groupBy(DB::raw('EXTRACT(MONTH FROM recibido_en)'))
                ->get()
                ->keyBy('mes');

            $mesesNombres = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ];

            $resultado = [];
            for ($m = 1; $m <= 12; $m++) {
                $fila = $filas->get($m);
                $item = [
                    'mes_numero' => $m,
                    'mes' => $mesesNombres[$m],
                    'total_atenciones' => (int) ($fila->total_atenciones ?? 0),
                ];

                if ($puedeVerCostos) {
                    $item['costo_asumido'] = round((float) ($fila->costo_asumido ?? 0.0), 2);
                }

                $resultado[] = $item;
            }

            return $resultado;
        });
    }

    /**
     * Devoluciones por motivo, producto, vendedor y tasa de devolución global.
     *
     * @return array<string, mixed>
     */
    public function devolucionesAnalisis(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
        ];

        return $this->recordar('postventa_devoluciones_analisis', $usuario, $params, function () use ($desde, $hasta) {
            $baseDev = DB::table('devoluciones')
                ->where('estado', 'completada')
                ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta->toDateString()));

            // Por motivo
            $porMotivo = (clone $baseDev)->select([
                'motivo',
                DB::raw('COUNT(id) as cantidad'),
                DB::raw('SUM(total_devuelto) as monto_devuelto'),
            ])
                ->groupBy('motivo')
                ->orderByDesc('cantidad')
                ->get()
                ->map(fn ($r) => [
                    'motivo' => (string) $r->motivo,
                    'cantidad' => (int) $r->cantidad,
                    'monto_devuelto' => round((float) $r->monto_devuelto, 2),
                ])->toArray();

            // Por producto
            $porProducto = DB::table('devolucion_detalles')
                ->join('devoluciones', 'devoluciones.id', '=', 'devolucion_detalles.devolucion_id')
                ->join('productos', 'productos.id', '=', 'devolucion_detalles.producto_id')
                ->where('devoluciones.estado', 'completada')
                ->when($desde, fn ($q) => $q->whereDate('devoluciones.created_at', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('devoluciones.created_at', '<=', $hasta->toDateString()))
                ->select([
                    'productos.id as producto_id',
                    'productos.nombre as producto_nombre',
                    'productos.sku',
                    DB::raw('SUM(devolucion_detalles.cantidad) as total_devuelto_unidades'),
                    DB::raw('SUM(devolucion_detalles.monto_devuelto) as total_monto'),
                ])
                ->groupBy('productos.id', 'productos.nombre', 'productos.sku')
                ->orderByDesc('total_devuelto_unidades')
                ->get()
                ->map(fn ($r) => [
                    'producto_id' => (int) $r->producto_id,
                    'producto' => (string) $r->producto_nombre,
                    'sku' => (string) $r->sku,
                    'total_devuelto_unidades' => (int) $r->total_devuelto_unidades,
                    'total_monto' => round((float) $r->total_monto, 2),
                ])->toArray();

            // Tasa de devolución global (Total devuelto / Total vendido)
            $totalVentas = (float) DB::table('ventas')
                ->where('estado', 'completada')
                ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta->toDateString()))
                ->sum('total');

            $montoDevueltoTotal = (float) (clone $baseDev)->sum('total_devuelto');
            $tasaDevolucion = $totalVentas > 0 ? round(($montoDevueltoTotal / $totalVentas) * 100, 2) : 0.0;

            return [
                'por_motivo' => $porMotivo,
                'por_producto' => $porProducto,
                'total_monto_devuelto' => round($montoDevueltoTotal, 2),
                'total_ventas_periodo' => round($totalVentas, 2),
                'tasa_devolucion_porcentaje' => $tasaDevolucion,
            ];
        });
    }

    /**
     * Garantías por vencer (próximos 30 días).
     *
     * @return array<int, array<string, mixed>>
     */
    public function garantiasPorVencer(User $usuario, int $dias = 30): array
    {
        $hoy = Carbon::today();
        $limite = Carbon::today()->addDays($dias);

        $params = ['dias' => $dias];

        return $this->recordar('postventa_garantias_por_vencer', $usuario, $params, function () use ($hoy, $limite) {
            $filas = DB::table('garantias')
                ->join('series', 'series.id', '=', 'garantias.serie_id')
                ->join('productos', 'productos.id', '=', 'series.producto_id')
                ->join('clientes', 'clientes.id', '=', 'garantias.cliente_id')
                ->where('garantias.estado', 'vigente')
                ->whereBetween('garantias.fecha_fin', [$hoy->toDateString(), $limite->toDateString()])
                ->select([
                    'garantias.id as garantia_id',
                    'series.numero_serie',
                    'productos.nombre as producto_nombre',
                    'clientes.nombre_razon_social as cliente_nombre',
                    'clientes.telefono as cliente_telefono',
                    'garantias.fecha_fin',
                ])
                ->orderBy('garantias.fecha_fin', 'asc')
                ->get();

            return $filas->map(fn ($r) => [
                'garantia_id' => (int) $r->garantia_id,
                'numero_serie' => (string) $r->numero_serie,
                'producto' => (string) $r->producto_nombre,
                'cliente' => (string) $r->cliente_nombre,
                'telefono' => (string) ($r->cliente_telefono ?? ''),
                'fecha_fin' => (string) $r->fecha_fin,
                'dias_restantes' => (int) Carbon::parse($r->fecha_fin)->diffInDays(Carbon::today()),
            ])->toArray();
        });
    }
}
