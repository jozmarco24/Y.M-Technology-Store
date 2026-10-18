<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoOrdenServicio;
use App\Enums\TipoOrdenServicio;
use App\Models\OrdenServicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReporteServicioService
{
    /**
     * Resumen de órdenes agrupadas por estado actual.
     *
     * @return array<string, int>
     */
    public function ordenesPorEstado(): array
    {
        $conteo = OrdenServicio::groupBy('estado')
            ->select('estado', DB::raw('count(*) as total'))
            ->pluck('total', 'estado')
            ->toArray();

        $resultado = [];
        foreach (EstadoOrdenServicio::cases() as $estado) {
            $resultado[$estado->value] = (int) ($conteo[$estado->value] ?? 0);
        }

        return $resultado;
    }

    /**
     * Tiempo promedio de reparación en horas o días para órdenes que hayan sido reparadas o entregadas.
     *
     * @return array{horas: float, dias: float, total_ordenes: int}
     */
    public function tiempoPromedioReparacion(): array
    {
        $ordenes = OrdenServicio::whereIn('estado', [EstadoOrdenServicio::REPARADO, EstadoOrdenServicio::ENTREGADO])
            ->whereNotNull('recibido_en')
            ->get();

        if ($ordenes->isEmpty()) {
            return ['horas' => 0.0, 'dias' => 0.0, 'total_ordenes' => 0];
        }

        $totalMinutos = 0;
        $conteoValido = 0;

        foreach ($ordenes as $orden) {
            // Buscamos el evento donde pasó a REPARADO
            $eventoReparado = $orden->eventos()
                ->where('estado_nuevo', EstadoOrdenServicio::REPARADO)
                ->first();

            $fechaFinal = $eventoReparado?->created_at ?? $orden->entregado_en ?? $orden->updated_at;

            if ($fechaFinal && $orden->recibido_en) {
                $diff = $orden->recibido_en->diffInMinutes($fechaFinal);
                $totalMinutos += $diff;
                $conteoValido++;
            }
        }

        if ($conteoValido === 0) {
            return ['horas' => 0.0, 'dias' => 0.0, 'total_ordenes' => 0];
        }

        $promedioMinutos = $totalMinutos / $conteoValido;
        $promedioHoras = round($promedioMinutos / 60, 2);
        $promedioDias = round($promedioHoras / 24, 2);

        return [
            'horas' => $promedioHoras,
            'dias' => $promedioDias,
            'total_ordenes' => $conteoValido,
        ];
    }

    /**
     * Equipos/modelos con mayor número de fallas reportadas.
     *
     * @return Collection<int, array{producto_id: int|null, producto_nombre: string, total_fallas: int}>
     */
    public function equiposConMayorNumeroDeFallas(int $limite = 10): Collection
    {
        $resultados = DB::table('ordenes_servicio')
            ->leftJoin('productos', 'ordenes_servicio.producto_id', '=', 'productos.id')
            ->select(
                'ordenes_servicio.producto_id',
                'productos.nombre as producto_nombre',
                'ordenes_servicio.equipo_descripcion',
                DB::raw('count(*) as total')
            )
            ->groupBy('ordenes_servicio.producto_id', 'productos.nombre', 'ordenes_servicio.equipo_descripcion')
            ->orderByDesc('total')
            ->limit($limite)
            ->get();

        return $resultados->map(function ($fila) {
            $nombre = (string) ($fila->producto_nombre ?? ($fila->equipo_descripcion ?: 'Equipo Externo / Sin Catálogo'));

            return [
                'producto_id' => $fila->producto_id ? (int) $fila->producto_id : null,
                'producto_nombre' => $nombre,
                'total_fallas' => (int) $fila->total,
            ];
        });
    }

    /**
     * Costo de repuestos y garantías asumidas por la tienda agrupado por mes (Solo Administrador).
     *
     * @return Collection<int, array{anio: int, mes: int, total_costo: float, total_ordenes: int}>
     */
    public function costoGarantiasAsumidasPorMes(User $usuario, ?int $anio = null): Collection
    {
        if (! $usuario->hasRole('admin')) {
            throw new \RuntimeException('Solo los usuarios administradores pueden consultar los costos de garantías asumidas.');
        }

        $anioFiltro = $anio ?? (int) Carbon::now()->year;

        // Sumar costos de repuestos de órdenes tipo GARANTIA
        $costos = DB::table('orden_servicio_repuestos')
            ->join('ordenes_servicio', 'orden_servicio_repuestos.orden_servicio_id', '=', 'ordenes_servicio.id')
            ->where('ordenes_servicio.tipo', TipoOrdenServicio::GARANTIA->value)
            ->whereYear('orden_servicio_repuestos.created_at', $anioFiltro)
            ->select(
                DB::raw('EXTRACT(YEAR FROM orden_servicio_repuestos.created_at) as anio'),
                DB::raw('EXTRACT(MONTH FROM orden_servicio_repuestos.created_at) as mes'),
                DB::raw('SUM(orden_servicio_repuestos.costo_unitario * orden_servicio_repuestos.cantidad) as total_costo'),
                DB::raw('COUNT(DISTINCT ordenes_servicio.id) as total_ordenes')
            )
            ->groupBy(
                DB::raw('EXTRACT(YEAR FROM orden_servicio_repuestos.created_at)'),
                DB::raw('EXTRACT(MONTH FROM orden_servicio_repuestos.created_at)')
            )
            ->orderBy('mes')
            ->get();

        return $costos->map(function ($fila) {
            return [
                'anio' => (int) $fila->anio,
                'mes' => (int) $fila->mes,
                'total_costo' => round((float) $fila->total_costo, 2),
                'total_ordenes' => (int) $fila->total_ordenes,
            ];
        });
    }

    /**
     * Órdenes de servicio atrasadas respecto a su fecha_promesa.
     *
     * @return Collection<int, OrdenServicio>
     */
    public function ordenesAtrasadas(): Collection
    {
        $hoy = Carbon::today()->toDateString();

        return OrdenServicio::whereNotNull('fecha_promesa')
            ->whereDate('fecha_promesa', '<', $hoy)
            ->whereNotIn('estado', [
                EstadoOrdenServicio::REPARADO->value,
                EstadoOrdenServicio::ENTREGADO->value,
                EstadoOrdenServicio::CANCELADO->value,
                EstadoOrdenServicio::SIN_SOLUCION->value,
            ])
            ->with(['cliente', 'tecnico', 'serie'])
            ->orderBy('fecha_promesa')
            ->get();
    }
}
