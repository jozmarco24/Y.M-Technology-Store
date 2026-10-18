<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EstadoVenta;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsVentasOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $hoy = Carbon::today();
        $inicioMes = Carbon::now()->startOfMonth();

        // Ventas de hoy
        $ventasHoy = (float) Venta::where('estado', EstadoVenta::COMPLETADA->value)
            ->whereDate('created_at', $hoy)
            ->sum('total');

        $conteoHoy = Venta::where('estado', EstadoVenta::COMPLETADA->value)
            ->whereDate('created_at', $hoy)
            ->count();

        // Ventas del mes
        $ventasMes = (float) Venta::where('estado', EstadoVenta::COMPLETADA->value)
            ->whereDate('created_at', '>=', $inicioMes)
            ->sum('total');

        $conteoMes = Venta::where('estado', EstadoVenta::COMPLETADA->value)
            ->whereDate('created_at', '>=', $inicioMes)
            ->count();

        // Ticket promedio del mes
        $ticketPromedio = $conteoMes > 0 ? round($ventasMes / $conteoMes, 2) : 0.0;

        $stats = [
            Stat::make('Ventas de Hoy', 'S/ '.number_format($ventasHoy, 2))
                ->description("{$conteoHoy} transacciones completadas")
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),

            Stat::make('Ventas del Mes', 'S/ '.number_format($ventasMes, 2))
                ->description("{$conteoMes} transacciones en el mes actual")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make('Ticket Promedio', 'S/ '.number_format($ticketPromedio, 2))
                ->description('Promedio por venta del mes')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),
        ];

        // Margen del mes: SOLO visible para admin
        /** @var User|null $user */
        $user = Auth::user();
        if ($user && $user->hasRole('admin')) {
            $costoTotalMes = (float) VentaDetalle::query()
                ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                ->where('ventas.estado', EstadoVenta::COMPLETADA->value)
                ->whereDate('ventas.created_at', '>=', $inicioMes)
                ->sum(\DB::raw('venta_detalles.cantidad * venta_detalles.costo_unitario'));

            $margenMes = round($ventasMes - $costoTotalMes, 2);
            $porcentajeMargen = $ventasMes > 0 ? round(($margenMes / $ventasMes) * 100, 1) : 0;

            $stats[] = Stat::make('Margen del Mes (Admin)', 'S/ '.number_format($margenMes, 2))
                ->description("Rentabilidad bruta ({$porcentajeMargen}%)")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning');
        }

        return $stats;
    }
}
