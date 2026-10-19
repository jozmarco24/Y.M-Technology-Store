<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\Configuracion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RentabilidadService extends BaseReporteService
{
    /**
     * Margen por producto.
     *
     * Fórmula:
     * venta_neta_sin_igv = subtotal (ya con descuento y sin IGV)
     * costo_total = cantidad * costo_unitario (copiado en venta_detalles)
     * margen_monto = venta_neta_sin_igv - costo_total
     * margen_porcentaje = (margen_monto / venta_neta_sin_igv) * 100
     *
     * Si el usuario no tiene permiso 'reportes.ver_margen', se omiten 'costo_total', 'margen_monto' y 'margen_porcentaje'.
     *
     * @return array<int, array<string, mixed>>
     */
    public function margenPorProducto(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null,
        ?string $canal = null,
        int $limite = 50
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
            'canal' => $canal,
            'limite' => $limite,
        ];

        return $this->recordar('margen_por_producto', $usuario, $params, function () use ($usuario, $desde, $hasta, $canal, $limite) {
            $query = DB::table('venta_detalles')
                ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                ->join('productos', 'productos.id', '=', 'venta_detalles.producto_id')
                ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
                ->leftJoin('marcas', 'marcas.id', '=', 'productos.marca_id')
                ->where('ventas.estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('ventas.vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($query, $desde, $hasta, $canal, 'ventas.created_at', 'ventas.canal');

            $selects = [
                'productos.id as producto_id',
                'productos.nombre as producto_nombre',
                'productos.sku',
                'categorias.nombre as categoria_nombre',
                'marcas.nombre as marca_nombre',
                DB::raw('SUM(venta_detalles.cantidad) as unidades_vendidas'),
                DB::raw('SUM(venta_detalles.subtotal) as venta_neta_sin_igv'),
            ];

            if ($this->puedeVerMargen($usuario)) {
                $selects[] = DB::raw('SUM(venta_detalles.cantidad * venta_detalles.costo_unitario) as costo_total');
                $selects[] = DB::raw('SUM(venta_detalles.subtotal - (venta_detalles.cantidad * venta_detalles.costo_unitario)) as margen_monto');
            }

            $query->select($selects)
                ->groupBy('productos.id', 'productos.nombre', 'productos.sku', 'categorias.nombre', 'marcas.nombre')
                ->orderByDesc('venta_neta_sin_igv')
                ->limit($limite);

            $resultados = $query->get();

            return $resultados->map(function ($row) use ($usuario) {
                $item = [
                    'producto_id' => (int) $row->producto_id,
                    'producto_nombre' => (string) $row->producto_nombre,
                    'sku' => (string) $row->sku,
                    'categoria' => (string) ($row->categoria_nombre ?? 'Sin categoría'),
                    'marca' => (string) ($row->marca_nombre ?? 'Sin marca'),
                    'unidades_vendidas' => (int) $row->unidades_vendidas,
                    'venta_neta_sin_igv' => round((float) $row->venta_neta_sin_igv, 2),
                ];

                if ($this->puedeVerMargen($usuario)) {
                    $costoTotal = round((float) $row->costo_total, 2);
                    $margenMonto = round((float) $row->margen_monto, 2);
                    $ventaNeta = (float) $row->venta_neta_sin_igv;
                    $margenPorc = $ventaNeta > 0 ? round(($margenMonto / $ventaNeta) * 100, 2) : 0.0;

                    $item['costo_total'] = $costoTotal;
                    $item['margen_monto'] = $margenMonto;
                    $item['margen_porcentaje'] = $margenPorc;
                }

                return $item;
            })->toArray();
        });
    }

    /**
     * Margen por categoría.
     *
     * @return array<int, array<string, mixed>>
     */
    public function margenPorCategoria(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null,
        ?string $canal = null
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
            'canal' => $canal,
        ];

        return $this->recordar('margen_por_categoria', $usuario, $params, function () use ($usuario, $desde, $hasta, $canal) {
            $query = DB::table('venta_detalles')
                ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                ->join('productos', 'productos.id', '=', 'venta_detalles.producto_id')
                ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
                ->where('ventas.estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('ventas.vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($query, $desde, $hasta, $canal, 'ventas.created_at', 'ventas.canal');

            $selects = [
                DB::raw("COALESCE(categorias.nombre, 'Sin categoría') as categoria_nombre"),
                DB::raw('SUM(venta_detalles.cantidad) as unidades_vendidas'),
                DB::raw('SUM(venta_detalles.subtotal) as venta_neta_sin_igv'),
            ];

            if ($this->puedeVerMargen($usuario)) {
                $selects[] = DB::raw('SUM(venta_detalles.cantidad * venta_detalles.costo_unitario) as costo_total');
                $selects[] = DB::raw('SUM(venta_detalles.subtotal - (venta_detalles.cantidad * venta_detalles.costo_unitario)) as margen_monto');
            }

            $query->select($selects)
                ->groupBy('categorias.nombre')
                ->orderByDesc('venta_neta_sin_igv');

            return $query->get()->map(function ($row) use ($usuario) {
                $item = [
                    'categoria' => (string) $row->categoria_nombre,
                    'unidades_vendidas' => (int) $row->unidades_vendidas,
                    'venta_neta_sin_igv' => round((float) $row->venta_neta_sin_igv, 2),
                ];

                if ($this->puedeVerMargen($usuario)) {
                    $costoTotal = round((float) $row->costo_total, 2);
                    $margenMonto = round((float) $row->margen_monto, 2);
                    $ventaNeta = (float) $row->venta_neta_sin_igv;
                    $margenPorc = $ventaNeta > 0 ? round(($margenMonto / $ventaNeta) * 100, 2) : 0.0;

                    $item['costo_total'] = $costoTotal;
                    $item['margen_monto'] = $margenMonto;
                    $item['margen_porcentaje'] = $margenPorc;
                }

                return $item;
            })->toArray();
        });
    }

    /**
     * Margen por marca.
     *
     * @return array<int, array<string, mixed>>
     */
    public function margenPorMarca(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null,
        ?string $canal = null
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
            'canal' => $canal,
        ];

        return $this->recordar('margen_por_marca', $usuario, $params, function () use ($usuario, $desde, $hasta, $canal) {
            $query = DB::table('venta_detalles')
                ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                ->join('productos', 'productos.id', '=', 'venta_detalles.producto_id')
                ->leftJoin('marcas', 'marcas.id', '=', 'productos.marca_id')
                ->where('ventas.estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('ventas.vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($query, $desde, $hasta, $canal, 'ventas.created_at', 'ventas.canal');

            $selects = [
                DB::raw("COALESCE(marcas.nombre, 'Sin marca') as marca_nombre"),
                DB::raw('SUM(venta_detalles.cantidad) as unidades_vendidas'),
                DB::raw('SUM(venta_detalles.subtotal) as venta_neta_sin_igv'),
            ];

            if ($this->puedeVerMargen($usuario)) {
                $selects[] = DB::raw('SUM(venta_detalles.cantidad * venta_detalles.costo_unitario) as costo_total');
                $selects[] = DB::raw('SUM(venta_detalles.subtotal - (venta_detalles.cantidad * venta_detalles.costo_unitario)) as margen_monto');
            }

            $query->select($selects)
                ->groupBy('marcas.nombre')
                ->orderByDesc('venta_neta_sin_igv');

            return $query->get()->map(function ($row) use ($usuario) {
                $item = [
                    'marca' => (string) $row->marca_nombre,
                    'unidades_vendidas' => (int) $row->unidades_vendidas,
                    'venta_neta_sin_igv' => round((float) $row->venta_neta_sin_igv, 2),
                ];

                if ($this->puedeVerMargen($usuario)) {
                    $costoTotal = round((float) $row->costo_total, 2);
                    $margenMonto = round((float) $row->margen_monto, 2);
                    $ventaNeta = (float) $row->venta_neta_sin_igv;
                    $margenPorc = $ventaNeta > 0 ? round(($margenMonto / $ventaNeta) * 100, 2) : 0.0;

                    $item['costo_total'] = $costoTotal;
                    $item['margen_monto'] = $margenMonto;
                    $item['margen_porcentaje'] = $margenPorc;
                }

                return $item;
            })->toArray();
        });
    }

    /**
     * Margen por vendedor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function margenPorVendedor(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null,
        ?string $canal = null
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
            'canal' => $canal,
        ];

        return $this->recordar('margen_por_vendedor', $usuario, $params, function () use ($usuario, $desde, $hasta, $canal) {
            $query = DB::table('venta_detalles')
                ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                ->join('users', 'users.id', '=', 'ventas.vendedor_id')
                ->where('ventas.estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('ventas.vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($query, $desde, $hasta, $canal, 'ventas.created_at', 'ventas.canal');

            $selects = [
                'users.id as vendedor_id',
                'users.name as vendedor_nombre',
                DB::raw('COUNT(DISTINCT ventas.id) as total_ventas'),
                DB::raw('SUM(venta_detalles.cantidad) as unidades_vendidas'),
                DB::raw('SUM(venta_detalles.subtotal) as venta_neta_sin_igv'),
            ];

            if ($this->puedeVerMargen($usuario)) {
                $selects[] = DB::raw('SUM(venta_detalles.cantidad * venta_detalles.costo_unitario) as costo_total');
                $selects[] = DB::raw('SUM(venta_detalles.subtotal - (venta_detalles.cantidad * venta_detalles.costo_unitario)) as margen_monto');
            }

            $query->select($selects)
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('venta_neta_sin_igv');

            return $query->get()->map(function ($row) use ($usuario) {
                $item = [
                    'vendedor_id' => (int) $row->vendedor_id,
                    'vendedor' => (string) $row->vendedor_nombre,
                    'total_ventas' => (int) $row->total_ventas,
                    'unidades_vendidas' => (int) $row->unidades_vendidas,
                    'venta_neta_sin_igv' => round((float) $row->venta_neta_sin_igv, 2),
                ];

                if ($this->puedeVerMargen($usuario)) {
                    $costoTotal = round((float) $row->costo_total, 2);
                    $margenMonto = round((float) $row->margen_monto, 2);
                    $ventaNeta = (float) $row->venta_neta_sin_igv;
                    $margenPorc = $ventaNeta > 0 ? round(($margenMonto / $ventaNeta) * 100, 2) : 0.0;

                    $item['costo_total'] = $costoTotal;
                    $item['margen_monto'] = $margenMonto;
                    $item['margen_porcentaje'] = $margenPorc;
                }

                return $item;
            })->toArray();
        });
    }

    /**
     * Margen por canal (pos vs web).
     *
     * @return array<int, array<string, mixed>>
     */
    public function margenPorCanal(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
        ];

        return $this->recordar('margen_por_canal', $usuario, $params, function () use ($usuario, $desde, $hasta) {
            $query = DB::table('venta_detalles')
                ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                ->where('ventas.estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('ventas.vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($query, $desde, $hasta, null, 'ventas.created_at', 'ventas.canal');

            $selects = [
                'ventas.canal',
                DB::raw('COUNT(DISTINCT ventas.id) as total_pedidos'),
                DB::raw('SUM(venta_detalles.cantidad) as unidades_vendidas'),
                DB::raw('SUM(venta_detalles.subtotal) as venta_neta_sin_igv'),
            ];

            if ($this->puedeVerMargen($usuario)) {
                $selects[] = DB::raw('SUM(venta_detalles.cantidad * venta_detalles.costo_unitario) as costo_total');
                $selects[] = DB::raw('SUM(venta_detalles.subtotal - (venta_detalles.cantidad * venta_detalles.costo_unitario)) as margen_monto');
            }

            $query->select($selects)
                ->groupBy('ventas.canal')
                ->orderByDesc('venta_neta_sin_igv');

            return $query->get()->map(function ($row) use ($usuario) {
                $item = [
                    'canal' => (string) $row->canal,
                    'total_pedidos' => (int) $row->total_pedidos,
                    'unidades_vendidas' => (int) $row->unidades_vendidas,
                    'venta_neta_sin_igv' => round((float) $row->venta_neta_sin_igv, 2),
                ];

                if ($this->puedeVerMargen($usuario)) {
                    $costoTotal = round((float) $row->costo_total, 2);
                    $margenMonto = round((float) $row->margen_monto, 2);
                    $ventaNeta = (float) $row->venta_neta_sin_igv;
                    $margenPorc = $ventaNeta > 0 ? round(($margenMonto / $ventaNeta) * 100, 2) : 0.0;

                    $item['costo_total'] = $costoTotal;
                    $item['margen_monto'] = $margenMonto;
                    $item['margen_porcentaje'] = $margenPorc;
                }

                return $item;
            })->toArray();
        });
    }

    /**
     * Top 10 productos más y menos rentables.
     *
     * @return array{mas_rentables: array<int, array<string, mixed>>, menos_rentables: array<int, array<string, mixed>>}
     */
    public function topRentabilidad(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null,
        ?string $canal = null
    ): array {
        $todos = $this->margenPorProducto($usuario, $desde, $hasta, $canal, 500);

        if (! $this->puedeVerMargen($usuario)) {
            // Si no puede ver margen, ordenamos solo por volumen de venta neta
            $ordenados = collect($todos)->sortByDesc('venta_neta_sin_igv')->values();

            return [
                'mas_rentables' => $ordenados->take(10)->toArray(),
                'menos_rentables' => $ordenados->reverse()->take(10)->values()->toArray(),
            ];
        }

        $porMargen = collect($todos)->sortByDesc('margen_monto')->values();

        return [
            'mas_rentables' => $porMargen->take(10)->toArray(),
            'menos_rentables' => $porMargen->reverse()->take(10)->values()->toArray(),
        ];
    }

    /**
     * Ventas bajo costo o con margen menor a un umbral configurable.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ventasBajoMargen(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null,
        ?float $umbralPorcentaje = null
    ): array {
        if (! $this->puedeVerMargen($usuario)) {
            return [];
        }

        $umbral = $umbralPorcentaje ?? (float) Configuracion::obtener('umbral_margen_bajo', '10.0');

        $query = DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->join('productos', 'productos.id', '=', 'venta_detalles.producto_id')
            ->join('users', 'users.id', '=', 'ventas.vendedor_id')
            ->where('ventas.estado', 'completada');

        $this->aplicarFiltrosVentas($query, $desde, $hasta, null, 'ventas.created_at', 'ventas.canal');

        $filas = $query->select([
            'ventas.codigo as venta_codigo',
            'ventas.created_at as fecha_venta',
            'users.name as vendedor_nombre',
            'productos.id as producto_id',
            'productos.nombre as producto_nombre',
            'productos.sku',
            'venta_detalles.cantidad',
            'venta_detalles.precio_unitario',
            'venta_detalles.descuento',
            'venta_detalles.subtotal',
            'venta_detalles.costo_unitario',
            DB::raw('(venta_detalles.cantidad * venta_detalles.costo_unitario) as costo_total_linea'),
            DB::raw('(venta_detalles.subtotal - (venta_detalles.cantidad * venta_detalles.costo_unitario)) as margen_monto'),
        ])->get();

        return $filas->filter(function ($row) use ($umbral) {
            $ventaNeta = (float) $row->subtotal;
            $margenMonto = (float) $row->margen_monto;
            $porcentaje = $ventaNeta > 0 ? ($margenMonto / $ventaNeta) * 100 : 0.0;

            return $porcentaje <= $umbral || $margenMonto <= 0;
        })->map(function ($row) {
            $ventaNeta = (float) $row->subtotal;
            $margenMonto = (float) $row->margen_monto;
            $porcentaje = $ventaNeta > 0 ? round(($margenMonto / $ventaNeta) * 100, 2) : 0.0;

            return [
                'venta_codigo' => (string) $row->venta_codigo,
                'fecha_venta' => (string) $row->fecha_venta,
                'vendedor' => (string) $row->vendedor_nombre,
                'producto_id' => (int) $row->producto_id,
                'producto' => (string) $row->producto_nombre,
                'sku' => (string) $row->sku,
                'cantidad' => (int) $row->cantidad,
                'precio_unitario' => round((float) $row->precio_unitario, 2),
                'costo_unitario' => round((float) $row->costo_unitario, 2),
                'venta_neta' => round($ventaNeta, 2),
                'costo_total' => round((float) $row->costo_total_linea, 2),
                'margen_monto' => round($margenMonto, 2),
                'margen_porcentaje' => $porcentaje,
                'es_bajo_costo' => $margenMonto < 0,
            ];
        })->values()->toArray();
    }

    /**
     * Impacto de descuentos otorgados por vendedor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function impactoDescuentosPorVendedor(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $query = DB::table('ventas')
            ->join('users', 'users.id', '=', 'ventas.vendedor_id')
            ->where('ventas.estado', 'completada');

        if ($this->esVendedorRestringido($usuario)) {
            $query->where('ventas.vendedor_id', $usuario->id);
        }

        $this->aplicarFiltrosVentas($query, $desde, $hasta, null, 'ventas.created_at', 'ventas.canal');

        $query->select([
            'users.id as vendedor_id',
            'users.name as vendedor_nombre',
            DB::raw('COUNT(ventas.id) as total_ventas'),
            DB::raw('SUM(ventas.subtotal) as subtotal_acumulado'),
            DB::raw('SUM(ventas.descuento) as descuento_total_otorgado'),
            DB::raw('SUM(ventas.total) as total_cobrado'),
        ])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('descuento_total_otorgado');

        return $query->get()->map(function ($row) {
            $subtotal = (float) $row->subtotal_acumulado;
            $descuento = (float) $row->descuento_total_otorgado;
            $porcentajeDescuento = ($subtotal + $descuento) > 0
                ? round(($descuento / ($subtotal + $descuento)) * 100, 2)
                : 0.0;

            return [
                'vendedor_id' => (int) $row->vendedor_id,
                'vendedor' => (string) $row->vendedor_nombre,
                'total_ventas' => (int) $row->total_ventas,
                'subtotal_acumulado' => round($subtotal, 2),
                'descuento_total_otorgado' => round($descuento, 2),
                'total_cobrado' => round((float) $row->total_cobrado, 2),
                'porcentaje_descuento_efectivo' => $porcentajeDescuento,
            ];
        })->toArray();
    }
}
