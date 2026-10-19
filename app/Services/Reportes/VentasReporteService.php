<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VentasReporteService extends BaseReporteService
{
    /**
     * Comparativo mensual de ventas (año actual vs año anterior o rango).
     *
     * @return array<int, array<string, mixed>>
     */
    public function comparativoMensual(User $usuario, ?int $anio = null): array
    {
        $anioActual = $anio ?? (int) Carbon::now()->year;
        $anioAnterior = $anioActual - 1;

        $params = ['anio' => $anioActual];

        return $this->recordar('ventas_comparativo_mensual', $usuario, $params, function () use ($usuario, $anioActual, $anioAnterior) {
            $queryActual = DB::table('ventas')
                ->where('estado', 'completada')
                ->whereYear('created_at', $anioActual);

            $queryAnterior = DB::table('ventas')
                ->where('estado', 'completada')
                ->whereYear('created_at', $anioAnterior);

            if ($this->esVendedorRestringido($usuario)) {
                $queryActual->where('vendedor_id', $usuario->id);
                $queryAnterior->where('vendedor_id', $usuario->id);
            }

            $ventasActual = $queryActual->select([
                DB::raw('EXTRACT(MONTH FROM created_at) as mes'),
                DB::raw('COUNT(id) as total_pedidos'),
                DB::raw('SUM(total) as monto_total'),
            ])->groupBy(DB::raw('EXTRACT(MONTH FROM created_at)'))->get()->keyBy('mes');

            $ventasAnterior = $queryAnterior->select([
                DB::raw('EXTRACT(MONTH FROM created_at) as mes'),
                DB::raw('COUNT(id) as total_pedidos'),
                DB::raw('SUM(total) as monto_total'),
            ])->groupBy(DB::raw('EXTRACT(MONTH FROM created_at)'))->get()->keyBy('mes');

            $mesesNombres = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ];

            $resultado = [];
            for ($m = 1; $m <= 12; $m++) {
                $actual = (float) ($ventasActual->get($m)->monto_total ?? 0.0);
                $anterior = (float) ($ventasAnterior->get($m)->monto_total ?? 0.0);
                $pedidosActual = (int) ($ventasActual->get($m)->total_pedidos ?? 0);
                $variacion = $anterior > 0 ? round((($actual - $anterior) / $anterior) * 100, 2) : ($actual > 0 ? 100.0 : 0.0);

                $resultado[] = [
                    'mes_numero' => $m,
                    'mes_nombre' => $mesesNombres[$m],
                    'anio_actual_monto' => round($actual, 2),
                    'anio_actual_pedidos' => $pedidosActual,
                    'anio_anterior_monto' => round($anterior, 2),
                    'variacion_porcentaje' => $variacion,
                ];
            }

            return $resultado;
        });
    }

    /**
     * Ventas por día de la semana y por hora (Horas pico de atención).
     *
     * @return array{por_dia_semana: array<int, array<string, mixed>>, por_hora: array<int, array<string, mixed>>}
     */
    public function horasPico(
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

        return $this->recordar('ventas_horas_pico', $usuario, $params, function () use ($usuario, $desde, $hasta, $canal) {
            $query = DB::table('ventas')->where('estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($query, $desde, $hasta, $canal);

            // Por hora (0 a 23)
            $porHoraQuery = (clone $query)->select([
                DB::raw('EXTRACT(HOUR FROM created_at) as hora'),
                DB::raw('COUNT(id) as total_ventas'),
                DB::raw('SUM(total) as monto_total'),
            ])->groupBy(DB::raw('EXTRACT(HOUR FROM created_at)'))->orderBy('hora')->get()->keyBy('hora');

            // Por día de la semana (1 = Domingo / o PostgreSQL 0 = Domingo, 1 = Lunes)
            $porDiaQuery = (clone $query)->select([
                DB::raw('EXTRACT(DOW FROM created_at) as dia_semana'),
                DB::raw('COUNT(id) as total_ventas'),
                DB::raw('SUM(total) as monto_total'),
            ])->groupBy(DB::raw('EXTRACT(DOW FROM created_at)'))->orderBy('dia_semana')->get()->keyBy('dia_semana');

            $horas = [];
            for ($h = 0; $h < 24; $h++) {
                $fila = $porHoraQuery->get($h);
                $horas[] = [
                    'hora' => sprintf('%02d:00', $h),
                    'total_ventas' => (int) ($fila->total_ventas ?? 0),
                    'monto_total' => round((float) ($fila->monto_total ?? 0.0), 2),
                ];
            }

            $diasNombres = [
                0 => 'Domingo',
                1 => 'Lunes',
                2 => 'Martes',
                3 => 'Miércoles',
                4 => 'Jueves',
                5 => 'Viernes',
                6 => 'Sábado',
            ];

            $dias = [];
            foreach ($diasNombres as $dow => $nombre) {
                $fila = $porDiaQuery->get($dow);
                $dias[] = [
                    'dia_numero' => $dow,
                    'dia_nombre' => $nombre,
                    'total_ventas' => (int) ($fila->total_ventas ?? 0),
                    'monto_total' => round((float) ($fila->monto_total ?? 0.0), 2),
                ];
            }

            return [
                'por_dia_semana' => $dias,
                'por_hora' => $horas,
            ];
        });
    }

    /**
     * Métricas de ticket promedio, unidades por venta y totales consolidados.
     *
     * @return array<string, mixed>
     */
    public function metricasGenerales(
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

        return $this->recordar('ventas_metricas_generales', $usuario, $params, function () use ($usuario, $desde, $hasta, $canal) {
            $query = DB::table('ventas')->where('estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($query, $desde, $hasta, $canal);

            $ventasStats = (clone $query)->select([
                DB::raw('COUNT(id) as total_pedidos'),
                DB::raw('SUM(total) as total_facturado'),
                DB::raw('SUM(subtotal) as total_subtotal'),
                DB::raw('SUM(descuento) as total_descuentos'),
                DB::raw('SUM(igv) as total_igv'),
            ])->first();

            $totalPedidos = (int) ($ventasStats->total_pedidos ?? 0);
            $totalFacturado = (float) ($ventasStats->total_facturado ?? 0.0);
            $ticketPromedio = $totalPedidos > 0 ? round($totalFacturado / $totalPedidos, 2) : 0.0;

            // Total de unidades vendidas
            $unidadesQuery = DB::table('venta_detalles')
                ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                ->where('ventas.estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $unidadesQuery->where('ventas.vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($unidadesQuery, $desde, $hasta, $canal, 'ventas.created_at', 'ventas.canal');

            $totalUnidades = (int) $unidadesQuery->sum('venta_detalles.cantidad');
            $unidadesPorVenta = $totalPedidos > 0 ? round($totalUnidades / $totalPedidos, 2) : 0.0;

            // Devoluciones en el periodo para calcular Venta Neta real
            $devQuery = DB::table('devoluciones')
                ->join('ventas', 'ventas.id', '=', 'devoluciones.venta_id')
                ->where('devoluciones.estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $devQuery->where('ventas.vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($devQuery, $desde, $hasta, $canal, 'devoluciones.created_at', 'ventas.canal');

            $totalDevoluciones = (float) $devQuery->sum('devoluciones.total_devuelto');
            $ventaNeta = round($totalFacturado - $totalDevoluciones, 2);

            return [
                'total_pedidos' => $totalPedidos,
                'total_facturado' => round($totalFacturado, 2),
                'total_devoluciones' => round($totalDevoluciones, 2),
                'venta_neta' => $ventaNeta,
                'ticket_promedio' => $ticketPromedio,
                'total_unidades' => $totalUnidades,
                'unidades_por_venta' => $unidadesPorVenta,
                'total_descuentos' => round((float) ($ventasStats->total_descuentos ?? 0.0), 2),
            ];
        });
    }

    /**
     * Comparativa de canal: POS vs Web, conversión de pedidos web y carritos abandonados.
     *
     * @return array<string, mixed>
     */
    public function canalPosVsWeb(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
        ];

        return $this->recordar('ventas_canal_pos_web', $usuario, $params, function () use ($desde, $hasta) {
            // Ventas por canal
            $ventasCanal = DB::table('ventas')
                ->where('estado', 'completada')
                ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta->toDateString()))
                ->select([
                    'canal',
                    DB::raw('COUNT(id) as total_pedidos'),
                    DB::raw('SUM(total) as monto_total'),
                ])
                ->groupBy('canal')
                ->get()
                ->keyBy('canal');

            $posPedidos = (int) ($ventasCanal->get('pos')->total_pedidos ?? 0);
            $posMonto = round((float) ($ventasCanal->get('pos')->monto_total ?? 0.0), 2);

            $webPedidos = (int) ($ventasCanal->get('web')->total_pedidos ?? 0);
            $webMonto = round((float) ($ventasCanal->get('web')->monto_total ?? 0.0), 2);

            // Embudo de pedidos web (creados vs pagados vs expirados vs cancelados)
            $pedidosWebQuery = DB::table('pedidos')
                ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta->toDateString()))
                ->select([
                    'estado',
                    DB::raw('COUNT(id) as cantidad'),
                ])
                ->groupBy('estado')
                ->get()
                ->keyBy('estado');

            $webTotalCreados = DB::table('pedidos')
                ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta->toDateString()))
                ->count();

            $webPagados = (int) ($pedidosWebQuery->get('pagado')->cantidad ?? 0)
                + (int) ($pedidosWebQuery->get('preparando')->cantidad ?? 0)
                + (int) ($pedidosWebQuery->get('enviado')->cantidad ?? 0)
                + (int) ($pedidosWebQuery->get('entregado')->cantidad ?? 0);

            $webExpirados = (int) ($pedidosWebQuery->get('expirado')->cantidad ?? 0);
            $webCancelados = (int) ($pedidosWebQuery->get('cancelado')->cantidad ?? 0);

            $tasaConversionWeb = $webTotalCreados > 0
                ? round(($webPagados / $webTotalCreados) * 100, 2)
                : 0.0;

            // Carritos abandonados (expirados sin pedido asociado)
            $carritosAbandonados = DB::table('carritos')
                ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta->toDateString()))
                ->where('expira_en', '<', Carbon::now())
                ->count();

            return [
                'pos' => [
                    'pedidos' => $posPedidos,
                    'monto' => $posMonto,
                ],
                'web' => [
                    'pedidos' => $webPedidos,
                    'monto' => $webMonto,
                    'pedidos_totales_iniciados' => $webTotalCreados,
                    'pedidos_pagados' => $webPagados,
                    'pedidos_expirados' => $webExpirados,
                    'pedidos_cancelados' => $webCancelados,
                    'tasa_conversion_porcentaje' => $tasaConversionWeb,
                    'carritos_abandonados' => $carritosAbandonados,
                ],
            ];
        });
    }

    /**
     * Top clientes y métricas de nuevos vs recurrentes.
     *
     * @return array{top_clientes: array<int, array<string, mixed>>, clientes_nuevos: int, clientes_recurrentes: int}
     */
    public function clientesAnalisis(
        User $usuario,
        ?Carbon $desde = null,
        ?Carbon $hasta = null,
        int $limite = 20
    ): array {
        $params = [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
            'limite' => $limite,
        ];

        return $this->recordar('ventas_clientes_analisis', $usuario, $params, function () use ($usuario, $desde, $hasta, $limite) {
            $query = DB::table('ventas')
                ->join('clientes', 'clientes.id', '=', 'ventas.cliente_id')
                ->where('ventas.estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('ventas.vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($query, $desde, $hasta, null);

            $topClientes = (clone $query)->select([
                'clientes.id as cliente_id',
                'clientes.nombre_razon_social as cliente_nombre',
                'clientes.numero_documento',
                'clientes.tipo_documento',
                DB::raw('COUNT(ventas.id) as total_compras'),
                DB::raw('SUM(ventas.total) as total_gastado'),
            ])
                ->groupBy('clientes.id', 'clientes.nombre_razon_social', 'clientes.numero_documento', 'clientes.tipo_documento')
                ->orderByDesc('total_gastado')
                ->limit($limite)
                ->get()
                ->map(fn ($r) => [
                    'cliente_id' => (int) $r->cliente_id,
                    'nombre' => (string) $r->cliente_nombre,
                    'documento' => (string) $r->numero_documento,
                    'tipo_documento' => (string) $r->tipo_documento,
                    'total_compras' => (int) $r->total_compras,
                    'total_gastado' => round((float) $r->total_gastado, 2),
                ])->toArray();

            // Clientes nuevos vs recurrentes en el periodo
            $clientesEnPeriodo = (clone $query)->distinct()->pluck('ventas.cliente_id');

            $nuevos = 0;
            $recurrentes = 0;

            foreach ($clientesEnPeriodo as $clienteId) {
                // Verificar si tuvo ventas antes de la fecha $desde
                $tuvoAntes = DB::table('ventas')
                    ->where('cliente_id', $clienteId)
                    ->where('estado', 'completada')
                    ->when($desde, fn ($q) => $q->whereDate('created_at', '<', $desde->toDateString()))
                    ->exists();

                if ($tuvoAntes) {
                    $recurrentes++;
                } else {
                    $nuevos++;
                }
            }

            return [
                'top_clientes' => $topClientes,
                'clientes_nuevos' => $nuevos,
                'clientes_recurrentes' => $recurrentes,
            ];
        });
    }

    /**
     * Ventas por método de pago.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ventasPorMetodoPago(
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

        return $this->recordar('ventas_por_metodo_pago', $usuario, $params, function () use ($usuario, $desde, $hasta, $canal) {
            $query = DB::table('venta_pagos')
                ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
                ->where('ventas.estado', 'completada');

            if ($this->esVendedorRestringido($usuario)) {
                $query->where('ventas.vendedor_id', $usuario->id);
            }

            $this->aplicarFiltrosVentas($query, $desde, $hasta, $canal, 'ventas.created_at', 'ventas.canal');

            $filas = $query->select([
                'venta_pagos.metodo',
                DB::raw('COUNT(venta_pagos.id) as cantidad_transacciones'),
                DB::raw('SUM(venta_pagos.monto) as total_monto'),
            ])
                ->groupBy('venta_pagos.metodo')
                ->orderByDesc('total_monto')
                ->get();

            $totalGeneral = (float) $filas->sum('total_monto');

            return $filas->map(function ($row) use ($totalGeneral) {
                $monto = (float) $row->total_monto;
                $porcentaje = $totalGeneral > 0 ? round(($monto / $totalGeneral) * 100, 2) : 0.0;

                return [
                    'metodo' => (string) $row->metodo,
                    'cantidad_transacciones' => (int) $row->cantidad_transacciones,
                    'total_monto' => round($monto, 2),
                    'participacion_porcentaje' => $porcentaje,
                ];
            })->toArray();
        });
    }
}
