<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EstadoVenta;
use App\Models\Venta;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class VentasUltimos30DiasChart extends ChartWidget
{
    protected static ?string $heading = 'Evolución de Ventas (Últimos 30 Días)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $hace30Dias = Carbon::today()->subDays(29);

        $ventasPorDia = Venta::query()
            ->where('estado', EstadoVenta::COMPLETADA->value)
            ->whereDate('created_at', '>=', $hace30Dias)
            ->select(
                DB::raw('DATE(created_at) as fecha'),
                DB::raw('SUM(total) as total')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'fecha')
            ->toArray();

        $labels = [];
        $data = [];

        for ($i = 29; $i >= 0; $i--) {
            $dia = Carbon::today()->subDays($i);
            $fechaKey = $dia->format('Y-m-d');
            $labels[] = $dia->format('d/m');
            $data[] = round((float) ($ventasPorDia[$fechaKey] ?? 0.0), 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Vendido (S/)',
                    'data' => $data,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => 'start',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
