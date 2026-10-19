<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\Configuracion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventarioReporteService extends BaseReporteService
{
    /**
     * Valorización del inventario (suma del costo de series disponibles + stock sin series valorizado a precio compra ref).
     *
     * @return array{por_categoria: array<int, array<string, mixed>>, por_marca: array<int, array<string, mixed>>, total_general: array<string, mixed>}
     */
    public function valorizacion(User $usuario): array
    {
        $puedeVerCosto = $this->puedeVerMargen($usuario);

        return $this->recordar('inventario_valorizacion', $usuario, [], function () use ($puedeVerCosto) {
            // 1. Series disponibles con su costo unitario real de ingreso
            $seriesQuery = DB::table('series')
                ->join('productos', 'productos.id', '=', 'series.producto_id')
                ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
                ->leftJoin('marcas', 'marcas.id', '=', 'productos.marca_id')
                ->where('series.estado', 'disponible')
                ->select([
                    DB::raw("COALESCE(categorias.nombre, 'Sin categoría') as categoria"),
                    DB::raw("COALESCE(marcas.nombre, 'Sin marca') as marca"),
                    DB::raw('COUNT(series.id) as cantidad_series'),
                    DB::raw('SUM(series.costo) as costo_total_series'),
                ])
                ->groupBy('categorias.nombre', 'marcas.nombre')
                ->get();

            // 2. Agrupación por categoría
            $catMap = [];
            $marcaMap = [];
            $totalUnidades = 0;
            $totalValor = 0.0;

            foreach ($seriesQuery as $row) {
                $cant = (int) $row->cantidad_series;
                $costo = (float) $row->costo_total_series;

                $totalUnidades += $cant;
                $totalValor += $costo;

                $catName = (string) $row->categoria;
                if (! isset($catMap[$catName])) {
                    $catMap[$catName] = ['categoria' => $catName, 'unidades' => 0, 'valor_total' => 0.0];
                }
                $catMap[$catName]['unidades'] += $cant;
                $catMap[$catName]['valor_total'] += $costo;

                $marcaName = (string) $row->marca;
                if (! isset($marcaMap[$marcaName])) {
                    $marcaMap[$marcaName] = ['marca' => $marcaName, 'unidades' => 0, 'valor_total' => 0.0];
                }
                $marcaMap[$marcaName]['unidades'] += $cant;
                $marcaMap[$marcaName]['valor_total'] += $costo;
            }

            $porCategoria = array_values(array_map(function ($item) use ($puedeVerCosto) {
                $res = [
                    'categoria' => $item['categoria'],
                    'unidades' => $item['unidades'],
                ];
                if ($puedeVerCosto) {
                    $res['valor_total'] = round($item['valor_total'], 2);
                }

                return $res;
            }, $catMap));

            $porMarca = array_values(array_map(function ($item) use ($puedeVerCosto) {
                $res = [
                    'marca' => $item['marca'],
                    'unidades' => $item['unidades'],
                ];
                if ($puedeVerCosto) {
                    $res['valor_total'] = round($item['valor_total'], 2);
                }

                return $res;
            }, $marcaMap));

            $totalGeneral = [
                'total_unidades' => $totalUnidades,
            ];

            if ($puedeVerCosto) {
                $totalGeneral['valor_total'] = round($totalValor, 2);
            }

            return [
                'por_categoria' => $porCategoria,
                'por_marca' => $porMarca,
                'total_general' => $totalGeneral,
            ];
        });
    }

    /**
     * Rotación de inventario por producto:
     * Rotación = Ventas del periodo / Stock promedio (o actual).
     * Días de inventario = Días del periodo / Rotación.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rotacion(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $fechaInicio = $desde ?? Carbon::now()->subDays(30);
        $fechaFin = $hasta ?? Carbon::now();
        $diasPeriodo = max(1, $fechaInicio->diffInDays($fechaFin));

        $params = [
            'desde' => $fechaInicio->toDateString(),
            'hasta' => $fechaFin->toDateString(),
        ];

        return $this->recordar('inventario_rotacion', $usuario, $params, function () use ($usuario, $fechaInicio, $fechaFin, $diasPeriodo) {
            $puedeVerCosto = $this->puedeVerMargen($usuario);

            // Ventas en el periodo
            $ventas = DB::table('venta_detalles')
                ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                ->where('ventas.estado', 'completada')
                ->whereDate('ventas.created_at', '>=', $fechaInicio->toDateString())
                ->whereDate('ventas.created_at', '<=', $fechaFin->toDateString())
                ->select([
                    'venta_detalles.producto_id',
                    DB::raw('SUM(venta_detalles.cantidad) as total_vendido'),
                    DB::raw('SUM(venta_detalles.subtotal) as subtotal_vendido'),
                ])
                ->groupBy('venta_detalles.producto_id')
                ->get()
                ->keyBy('producto_id');

            // Stock actual disponible
            $stockActual = DB::table('series')
                ->where('estado', 'disponible')
                ->select(['producto_id', DB::raw('COUNT(id) as stock_disponible'), DB::raw('SUM(costo) as costo_stock')])
                ->groupBy('producto_id')
                ->get()
                ->keyBy('producto_id');

            $productos = DB::table('productos')
                ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
                ->where('productos.activo', true)
                ->select([
                    'productos.id',
                    'productos.nombre',
                    'productos.sku',
                    'categorias.nombre as categoria',
                ])
                ->get();

            return $productos->map(function ($prod) use ($ventas, $stockActual, $diasPeriodo, $puedeVerCosto) {
                $vendido = (int) ($ventas->get($prod->id)->total_vendido ?? 0);
                $stock = (int) ($stockActual->get($prod->id)->stock_disponible ?? 0);

                // Stock promedio aproximado: (stock_actual + unidades_vendidas) / 2
                $stockPromedio = max(1.0, ($stock + ($stock + $vendido)) / 2.0);
                $indiceRotacion = round($vendido / $stockPromedio, 2);
                $diasInventario = $indiceRotacion > 0 ? (int) round($diasPeriodo / $indiceRotacion) : 999;

                $item = [
                    'producto_id' => (int) $prod->id,
                    'producto' => (string) $prod->nombre,
                    'sku' => (string) $prod->sku,
                    'categoria' => (string) ($prod->categoria ?? 'Sin categoría'),
                    'unidades_vendidas' => $vendido,
                    'stock_actual' => $stock,
                    'indice_rotacion' => $indiceRotacion,
                    'dias_inventario' => $diasInventario,
                ];

                if ($puedeVerCosto) {
                    $item['costo_inventario'] = round((float) ($stockActual->get($prod->id)->costo_stock ?? 0), 2);
                }

                return $item;
            })->sortByDesc('unidades_vendidas')->values()->toArray();
        });
    }

    /**
     * Stock muerto: productos con stock disponible y sin ventas en N días (configurable).
     *
     * @return array<int, array<string, mixed>>
     */
    public function stockMuerto(User $usuario, ?int $diasSinVenta = null): array
    {
        $dias = $diasSinVenta ?? (int) Configuracion::obtener('dias_stock_muerto', '90');
        $fechaCorte = Carbon::now()->subDays($dias);
        $puedeVerCosto = $this->puedeVerMargen($usuario);

        $params = ['dias' => $dias];

        return $this->recordar('inventario_stock_muerto', $usuario, $params, function () use ($dias, $fechaCorte, $puedeVerCosto) {
            // Productos con series disponibles actualmente
            $productosConStock = DB::table('series')
                ->join('productos', 'productos.id', '=', 'series.producto_id')
                ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
                ->leftJoin('v_ultima_venta_producto', 'v_ultima_venta_producto.producto_id', '=', 'productos.id')
                ->where('series.estado', 'disponible')
                ->select([
                    'productos.id as producto_id',
                    'productos.nombre as producto_nombre',
                    'productos.sku',
                    'categorias.nombre as categoria_nombre',
                    DB::raw('COUNT(series.id) as stock_disponible'),
                    DB::raw('SUM(series.costo) as capital_inmovilizado'),
                    'v_ultima_venta_producto.ultima_venta_en',
                ])
                ->groupBy('productos.id', 'productos.nombre', 'productos.sku', 'categorias.nombre', 'v_ultima_venta_producto.ultima_venta_en')
                ->get();

            return $productosConStock->filter(function ($row) use ($fechaCorte) {
                // Si nunca se ha vendido o su última venta fue antes de la fecha corte
                if ($row->ultima_venta_en === null) {
                    return true;
                }

                return Carbon::parse($row->ultima_venta_en)->lt($fechaCorte);
            })->map(function ($row) use ($dias, $puedeVerCosto) {
                $diasInactivo = $row->ultima_venta_en !== null
                    ? (int) Carbon::parse($row->ultima_venta_en)->diffInDays(Carbon::now())
                    : $dias;

                $item = [
                    'producto_id' => (int) $row->producto_id,
                    'producto' => (string) $row->producto_nombre,
                    'sku' => (string) $row->sku,
                    'categoria' => (string) ($row->categoria_nombre ?? 'Sin categoría'),
                    'stock_disponible' => (int) $row->stock_disponible,
                    'ultima_venta_en' => $row->ultima_venta_en ? (string) $row->ultima_venta_en : 'Nunca vendido',
                    'dias_sin_venta' => $diasInactivo,
                ];

                if ($puedeVerCosto) {
                    $item['capital_inmovilizado'] = round((float) $row->capital_inmovilizado, 2);
                }

                return $item;
            })->sortByDesc('stock_disponible')->values()->toArray();
        });
    }

    /**
     * Sugerencia de reposición de inventario:
     * Promedio diario de ventas (últimos 30/60/90 días), cobertura en días y cantidad sugerida para cubrir N días deseados.
     * Considera 'stock_minimo'.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sugerenciaReposicion(
        User $usuario,
        int $diasAnalisis = 30,
        ?int $diasCoberturaDeseada = null
    ): array {
        $coberturaDeseada = $diasCoberturaDeseada ?? (int) Configuracion::obtener('dias_cobertura_reposicion', '30');
        $fechaDesde = Carbon::now()->subDays($diasAnalisis);
        $puedeVerCosto = $this->puedeVerMargen($usuario);

        $params = [
            'dias_analisis' => $diasAnalisis,
            'cobertura_deseada' => $coberturaDeseada,
        ];

        return $this->recordar('inventario_reposicion', $usuario, $params, function () use ($diasAnalisis, $coberturaDeseada, $fechaDesde, $puedeVerCosto) {
            // Ventas históricas
            $ventas = DB::table('venta_detalles')
                ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                ->where('ventas.estado', 'completada')
                ->where('ventas.created_at', '>=', $fechaDesde)
                ->select([
                    'venta_detalles.producto_id',
                    DB::raw('SUM(venta_detalles.cantidad) as total_vendido'),
                ])
                ->groupBy('venta_detalles.producto_id')
                ->get()
                ->keyBy('producto_id');

            // Stock actual disponible
            $stockActual = DB::table('series')
                ->where('estado', 'disponible')
                ->select(['producto_id', DB::raw('COUNT(id) as stock')])
                ->groupBy('producto_id')
                ->get()
                ->keyBy('producto_id');

            $productos = DB::table('productos')
                ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
                ->where('productos.activo', true)
                ->select([
                    'productos.id',
                    'productos.nombre',
                    'productos.sku',
                    'productos.stock_minimo',
                    'productos.precio_compra_referencial',
                    'categorias.nombre as categoria',
                ])
                ->get();

            return $productos->map(function ($prod) use ($ventas, $stockActual, $diasAnalisis, $coberturaDeseada, $puedeVerCosto) {
                $totalVendido = (int) ($ventas->get($prod->id)->total_vendido ?? 0);
                $stock = (int) ($stockActual->get($prod->id)->stock ?? 0);
                $stockMinimo = (int) $prod->stock_minimo;

                $promedioDiario = round($totalVendido / max(1, $diasAnalisis), 3);
                $diasCoberturaActual = $promedioDiario > 0 ? (int) floor($stock / $promedioDiario) : 999;

                // Demanda esperada para cubrir la cantidad de días configurada
                $demandaEsperada = (int) ceil($promedioDiario * $coberturaDeseada);
                // Sugerido = Demanda esperada + Stock Mínimo - Stock Actual
                $sugerido = max(0, ($demandaEsperada + $stockMinimo) - $stock);

                $item = [
                    'producto_id' => (int) $prod->id,
                    'producto' => (string) $prod->nombre,
                    'sku' => (string) $prod->sku,
                    'categoria' => (string) ($prod->categoria ?? 'Sin categoría'),
                    'stock_actual' => $stock,
                    'stock_minimo' => $stockMinimo,
                    'promedio_diario_ventas' => $promedioDiario,
                    'dias_cobertura_actual' => $diasCoberturaActual,
                    'cobertura_objetivo_dias' => $coberturaDeseada,
                    'cantidad_sugerida' => $sugerido,
                    'requiere_reposicion' => $sugerido > 0 || $stock <= $stockMinimo,
                ];

                if ($puedeVerCosto) {
                    $costoUnitario = (float) $prod->precio_compra_referencial;
                    $item['costo_estimado_reposicion'] = round($sugerido * $costoUnitario, 2);
                }

                return $item;
            })->filter(fn ($p) => $p['requiere_reposicion'])->sortByDesc('cantidad_sugerida')->values()->toArray();
        });
    }

    /**
     * Clasificación ABC de productos por contribución al margen (o ventas netas si no es admin).
     *
     * A: 80% de la contribución acumulada.
     * B: Siguiente 15% (80% al 95%).
     * C: Último 5% (95% al 100%).
     *
     * @return array<int, array<string, mixed>>
     */
    public function clasificacionABC(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $puedeVerMargen = $this->puedeVerMargen($usuario);

        $query = DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->join('productos', 'productos.id', '=', 'venta_detalles.producto_id')
            ->where('ventas.estado', 'completada');

        $this->aplicarFiltrosVentas($query, $desde, $hasta, null, 'ventas.created_at', 'ventas.canal');

        $selects = [
            'productos.id as producto_id',
            'productos.nombre as producto_nombre',
            'productos.sku',
            DB::raw('SUM(venta_detalles.cantidad) as total_unidades'),
            DB::raw('SUM(venta_detalles.subtotal) as total_venta_neta'),
        ];

        if ($puedeVerMargen) {
            $selects[] = DB::raw('SUM(venta_detalles.subtotal - (venta_detalles.cantidad * venta_detalles.costo_unitario)) as margen_monto');
        }

        $items = $query->select($selects)
            ->groupBy('productos.id', 'productos.nombre', 'productos.sku')
            ->get();

        $criterio = $puedeVerMargen ? 'margen_monto' : 'total_venta_neta';

        // Ordenar descendentemente según el criterio
        $ordenados = $items->sortByDesc(fn ($row) => (float) $row->{$criterio})->values();

        $totalGeneral = (float) $ordenados->sum(fn ($row) => max(0.0, (float) $row->{$criterio}));

        $acumulado = 0.0;

        return $ordenados->map(function ($row) use (&$acumulado, $totalGeneral, $criterio, $puedeVerMargen) {
            $valor = max(0.0, (float) $row->{$criterio});
            $acumulado += $valor;
            $porcentajeAcumulado = $totalGeneral > 0 ? ($acumulado / $totalGeneral) * 100 : 100.0;

            if ($porcentajeAcumulado <= 80.0) {
                $clase = 'A';
            } elseif ($porcentajeAcumulado <= 95.0) {
                $clase = 'B';
            } else {
                $clase = 'C';
            }

            $item = [
                'producto_id' => (int) $row->producto_id,
                'producto' => (string) $row->producto_nombre,
                'sku' => (string) $row->sku,
                'total_unidades' => (int) $row->total_unidades,
                'total_venta_neta' => round((float) $row->total_venta_neta, 2),
                'clasificacion' => $clase,
                'porcentaje_acumulado' => round($porcentajeAcumulado, 2),
            ];

            if ($puedeVerMargen) {
                $item['margen_monto'] = round((float) $row->margen_monto, 2);
            }

            return $item;
        })->toArray();
    }

    /**
     * Antigüedad de series en inventario disponible (> X días en inventario).
     *
     * @return array<int, array<string, mixed>>
     */
    public function seriesAntiguas(User $usuario, int $diasMinimos = 60): array
    {
        $fechaCorte = Carbon::now()->subDays($diasMinimos);
        $puedeVerCosto = $this->puedeVerMargen($usuario);

        $query = DB::table('series')
            ->join('productos', 'productos.id', '=', 'series.producto_id')
            ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->where('series.estado', 'disponible')
            ->where('series.created_at', '<=', $fechaCorte)
            ->select([
                'series.id as serie_id',
                'series.numero_serie',
                'series.created_at as fecha_ingreso',
                'series.costo',
                'productos.id as producto_id',
                'productos.nombre as producto_nombre',
                'productos.sku',
                'categorias.nombre as categoria_nombre',
            ])
            ->orderBy('series.created_at', 'asc');

        return $query->get()->map(function ($row) use ($puedeVerCosto) {
            $dias = (int) Carbon::parse($row->fecha_ingreso)->diffInDays(Carbon::now());

            $item = [
                'serie_id' => (int) $row->serie_id,
                'numero_serie' => (string) $row->numero_serie,
                'producto_id' => (int) $row->producto_id,
                'producto' => (string) $row->producto_nombre,
                'sku' => (string) $row->sku,
                'categoria' => (string) ($row->categoria_nombre ?? 'Sin categoría'),
                'fecha_ingreso' => (string) $row->fecha_ingreso,
                'dias_antiguedad' => $dias,
            ];

            if ($puedeVerCosto) {
                $item['costo'] = round((float) $row->costo, 2);
            }

            return $item;
        })->toArray();
    }
}
