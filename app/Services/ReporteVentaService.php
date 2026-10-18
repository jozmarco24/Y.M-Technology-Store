<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoVenta;
use App\Enums\MetodoPago;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReporteVentaService
{
    /**
     * Ventas del día (o fecha específica) completadas.
     *
     * @return Collection<int, Venta>
     */
    public function ventasDelDia(?Carbon $fecha = null): Collection
    {
        $dia = $fecha ?? Carbon::today();

        return Venta::query()
            ->where('estado', EstadoVenta::COMPLETADA->value)
            ->whereDate('created_at', $dia)
            ->with(['cliente', 'vendedor', 'pagos', 'detalles'])
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Resumen de ventas agrupadas por vendedor.
     *
     * @return array<int, array{vendedor_id: int, vendedor: string, cantidad_ventas: int, total_vendido: float}>
     */
    public function ventasPorVendedor(?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $query = Venta::query()
            ->where('estado', EstadoVenta::COMPLETADA->value);

        if ($desde !== null) {
            $query->whereDate('created_at', '>=', $desde);
        }
        if ($hasta !== null) {
            $query->whereDate('created_at', '<=', $hasta);
        }

        $resumen = DB::table('ventas')
            ->join('users', 'users.id', '=', 'ventas.vendedor_id')
            ->where('ventas.estado', EstadoVenta::COMPLETADA->value)
            ->when($desde !== null, fn ($q) => $q->whereDate('ventas.created_at', '>=', $desde))
            ->when($hasta !== null, fn ($q) => $q->whereDate('ventas.created_at', '<=', $hasta))
            ->select(
                'ventas.vendedor_id',
                'users.name as vendedor_nombre',
                DB::raw('COUNT(ventas.id) as cantidad_ventas'),
                DB::raw('SUM(ventas.total) as total_vendido')
            )
            ->groupBy('ventas.vendedor_id', 'users.name')
            ->get();

        return $resumen->map(function ($fila) {
            return [
                'vendedor_id' => (int) $fila->vendedor_id,
                'vendedor' => (string) $fila->vendedor_nombre,
                'cantidad_ventas' => (int) $fila->cantidad_ventas,
                'total_vendido' => round((float) $fila->total_vendido, 2),
            ];
        })->toArray();
    }

    /**
     * Resumen de ventas agrupadas por método de pago.
     *
     * @return array<string, array{metodo: string, etiqueta: string, cantidad: int, total: float}>
     */
    public function ventasPorMetodoPago(?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $query = DB::table('venta_pagos')
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->where('ventas.estado', EstadoVenta::COMPLETADA->value);

        if ($desde !== null) {
            $query->whereDate('ventas.created_at', '>=', $desde);
        }
        if ($hasta !== null) {
            $query->whereDate('ventas.created_at', '<=', $hasta);
        }

        $resultado = $query->select(
            'venta_pagos.metodo',
            DB::raw('COUNT(venta_pagos.id) as cantidad'),
            DB::raw('SUM(venta_pagos.monto) as total')
        )
            ->groupBy('venta_pagos.metodo')
            ->get();

        $respuesta = [];
        foreach ($resultado as $fila) {
            $metodoVal = (string) $fila->metodo;
            $metodoEnum = MetodoPago::tryFrom($metodoVal);
            $etiqueta = $metodoEnum !== null ? $metodoEnum->etiqueta() : $metodoVal;

            $respuesta[$metodoVal] = [
                'metodo' => $metodoVal,
                'etiqueta' => $etiqueta,
                'cantidad' => (int) $fila->cantidad,
                'total' => round((float) $fila->total, 2),
            ];
        }

        return $respuesta;
    }

    /**
     * Productos más vendidos en cantidad y recaudación.
     *
     * @return array<int, array{producto_id: int, producto: string, cantidad_vendida: int, total_recaudado: float}>
     */
    public function productosMasVendidos(int $limite = 10, ?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $filas = DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->join('productos', 'productos.id', '=', 'venta_detalles.producto_id')
            ->where('ventas.estado', EstadoVenta::COMPLETADA->value)
            ->when($desde !== null, fn ($q) => $q->whereDate('ventas.created_at', '>=', $desde))
            ->when($hasta !== null, fn ($q) => $q->whereDate('ventas.created_at', '<=', $hasta))
            ->select(
                'venta_detalles.producto_id',
                'productos.nombre as producto_nombre',
                DB::raw('SUM(venta_detalles.cantidad) as total_cantidad'),
                DB::raw('SUM(venta_detalles.subtotal) as total_recaudado')
            )
            ->groupBy('venta_detalles.producto_id', 'productos.nombre')
            ->orderByDesc('total_cantidad')
            ->limit($limite)
            ->get();

        return $filas->map(function ($item) {
            return [
                'producto_id' => (int) $item->producto_id,
                'producto' => (string) $item->producto_nombre,
                'cantidad_vendida' => (int) $item->total_cantidad,
                'total_recaudado' => round((float) $item->total_recaudado, 2),
            ];
        })->toArray();
    }

    /**
     * Calcula margen de ganancia: total de ventas − costo total de los productos vendidos.
     * RESTRICCIÓN: Solo accesible por administradores (o con permiso reportes.ver_margen).
     *
     * @return array{
     *     total_ventas: float,
     *     total_costo: float,
     *     margen_bruto: float,
     *     porcentaje_margen: float,
     *     cantidad_ventas: int
     * }
     */
    public function calcularMargen(User $usuario, ?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        if (! $usuario->hasRole('admin') && ! $usuario->hasPermissionTo('reportes.ver_margen')) {
            throw new RuntimeException('No tienes permisos suficientes para visualizar márgenes de venta.');
        }

        $query = VentaDetalle::query()
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', EstadoVenta::COMPLETADA->value);

        if ($desde !== null) {
            $query->whereDate('ventas.created_at', '>=', $desde);
        }
        if ($hasta !== null) {
            $query->whereDate('ventas.created_at', '<=', $hasta);
        }

        $resultado = $query->select(
            DB::raw('COUNT(DISTINCT ventas.id) as cantidad_ventas'),
            DB::raw('SUM(venta_detalles.subtotal) as total_ingreso'),
            DB::raw('SUM(venta_detalles.cantidad * venta_detalles.costo_unitario) as total_costo')
        )->first();

        $totalIngreso = round((float) ($resultado?->total_ingreso ?? 0.0), 2);
        $totalCosto = round((float) ($resultado?->total_costo ?? 0.0), 2);
        $margenBruto = round($totalIngreso - $totalCosto, 2);

        $porcentaje = $totalIngreso > 0 ? round(($margenBruto / $totalIngreso) * 100, 2) : 0.0;

        return [
            'total_ventas' => $totalIngreso,
            'total_costo' => $totalCosto,
            'margen_bruto' => $margenBruto,
            'porcentaje_margen' => $porcentaje,
            'cantidad_ventas' => (int) ($resultado?->cantidad_ventas ?? 0),
        ];
    }
}
