<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ComprasReporteService extends BaseReporteService
{
    /**
     * Compras agrupadas por proveedor y periodo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function comprasPorProveedor(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
        ];

        return $this->recordar('compras_por_proveedor', $usuario, $params, function () use ($usuario, $desde, $hasta) {
            $puedeVerCostos = $this->puedeVerMargen($usuario);

            $query = DB::table('compras')
                ->join('proveedores', 'proveedores.id', '=', 'compras.proveedor_id')
                ->where('compras.estado', 'recibida')
                ->when($desde, fn ($q) => $q->whereDate('compras.created_at', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('compras.created_at', '<=', $hasta->toDateString()));

            $selects = [
                'proveedores.id as proveedor_id',
                'proveedores.razon_social',
                'proveedores.numero_documento',
                DB::raw('COUNT(compras.id) as cantidad_compras'),
            ];

            if ($puedeVerCostos) {
                $selects[] = DB::raw('SUM(compras.subtotal) as subtotal_compras');
                $selects[] = DB::raw('SUM(compras.igv) as igv_compras');
                $selects[] = DB::raw('SUM(compras.total) as total_compras');
            }

            $filas = $query->select($selects)
                ->groupBy('proveedores.id', 'proveedores.razon_social', 'proveedores.numero_documento')
                ->orderByDesc('cantidad_compras')
                ->get();

            return $filas->map(function ($row) use ($puedeVerCostos) {
                $item = [
                    'proveedor_id' => (int) $row->proveedor_id,
                    'razon_social' => (string) $row->razon_social,
                    'numero_documento' => (string) $row->numero_documento,
                    'cantidad_compras' => (int) $row->cantidad_compras,
                ];

                if ($puedeVerCostos) {
                    $item['subtotal_compras'] = round((float) $row->subtotal_compras, 2);
                    $item['igv_compras'] = round((float) $row->igv_compras, 2);
                    $item['total_compras'] = round((float) $row->total_compras, 2);
                }

                return $item;
            })->toArray();
        });
    }

    /**
     * Evolución histórica del costo de un producto específico (a lo largo de compras recibidas) y variación porcentual.
     *
     * @return array<int, array<string, mixed>>
     */
    public function evolucionCostoProducto(User $usuario, int $productoId): array
    {
        if (! $this->puedeVerMargen($usuario)) {
            return [];
        }

        $params = ['producto_id' => $productoId];

        return $this->recordar('compras_evolucion_costo', $usuario, $params, function () use ($productoId) {
            $filas = DB::table('compra_detalles')
                ->join('compras', 'compras.id', '=', 'compra_detalles.compra_id')
                ->join('proveedores', 'proveedores.id', '=', 'compras.proveedor_id')
                ->where('compra_detalles.producto_id', $productoId)
                ->where('compras.estado', 'recibida')
                ->select([
                    'compras.codigo as compra_codigo',
                    'compras.created_at as fecha_compra',
                    'proveedores.razon_social as proveedor_nombre',
                    'compra_detalles.cantidad',
                    'compra_detalles.costo_unitario',
                    'compra_detalles.subtotal',
                ])
                ->orderBy('compras.created_at', 'asc')
                ->get();

            $costoAnterior = null;

            return $filas->map(function ($row) use (&$costoAnterior) {
                $costoActual = (float) $row->costo_unitario;

                $variacion = 0.0;
                if ($costoAnterior !== null && $costoAnterior > 0) {
                    $variacion = round((($costoActual - $costoAnterior) / $costoAnterior) * 100, 2);
                }

                $costoAnterior = $costoActual;

                return [
                    'compra_codigo' => (string) $row->compra_codigo,
                    'fecha_compra' => (string) $row->fecha_compra,
                    'proveedor' => (string) $row->proveedor_nombre,
                    'cantidad' => (int) $row->cantidad,
                    'costo_unitario' => round($costoActual, 2),
                    'subtotal' => round((float) $row->subtotal, 2),
                    'variacion_porcentaje' => $variacion,
                ];
            })->toArray();
        });
    }

    /**
     * Comparativo de costo entre diferentes proveedores para un producto.
     *
     * @return array<int, array<string, mixed>>
     */
    public function comparativoCostoProveedores(User $usuario, int $productoId): array
    {
        if (! $this->puedeVerMargen($usuario)) {
            return [];
        }

        $params = ['producto_id' => $productoId];

        return $this->recordar('compras_comparativo_proveedores', $usuario, $params, function () use ($productoId) {
            $filas = DB::table('compra_detalles')
                ->join('compras', 'compras.id', '=', 'compra_detalles.compra_id')
                ->join('proveedores', 'proveedores.id', '=', 'compras.proveedor_id')
                ->where('compra_detalles.producto_id', $productoId)
                ->where('compras.estado', 'recibida')
                ->select([
                    'proveedores.id as proveedor_id',
                    'proveedores.razon_social',
                    DB::raw('COUNT(compras.id) as total_compras'),
                    DB::raw('SUM(compra_detalles.cantidad) as total_unidades_compradas'),
                    DB::raw('MIN(compra_detalles.costo_unitario) as costo_minimo'),
                    DB::raw('MAX(compra_detalles.costo_unitario) as costo_maximo'),
                    DB::raw('AVG(compra_detalles.costo_unitario) as costo_promedio'),
                    DB::raw('MAX(compras.created_at) as ultima_compra_en'),
                ])
                ->groupBy('proveedores.id', 'proveedores.razon_social')
                ->orderBy('costo_promedio', 'asc')
                ->get();

            return $filas->map(fn ($r) => [
                'proveedor_id' => (int) $r->proveedor_id,
                'razon_social' => (string) $r->razon_social,
                'total_compras' => (int) $r->total_compras,
                'total_unidades' => (int) $r->total_unidades_compradas,
                'costo_minimo' => round((float) $r->costo_minimo, 2),
                'costo_maximo' => round((float) $r->costo_maximo, 2),
                'costo_promedio' => round((float) $r->costo_promedio, 2),
                'ultima_compra_en' => (string) $r->ultima_compra_en,
            ])->toArray();
        });
    }
}
