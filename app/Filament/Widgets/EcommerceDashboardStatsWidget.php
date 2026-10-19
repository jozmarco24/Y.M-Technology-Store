<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EstadoPedido;
use App\Models\Carrito;
use App\Models\Pedido;
use App\Models\Reclamacion;
use App\Models\Venta;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EcommerceDashboardStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // 1. Pedidos pendientes de preparar
        $pendientesPreparar = Pedido::where('estado', EstadoPedido::PAGADO)->count();

        // 2. Pedidos registrados hoy
        $pedidosHoy = Pedido::whereDate('created_at', Carbon::today())->count();

        // 3. Ventas web del mes (monto total)
        $ventasMes = Venta::where('canal', 'web')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total');

        // 4. Carritos activos / abandonados (con más de 2 horas sin convertirse en pedido)
        $carritosAbandonados = Carrito::where('updated_at', '<', Carbon::now()->subHours(2))
            ->whereHas('items')
            ->count();

        // 5. Reclamaciones por vencer (menos de 7 días hábiles o pendientes)
        $reclamacionesPendientes = Reclamacion::whereIn('estado', ['pendiente', 'en_proceso'])->count();

        return [
            Stat::make('Por Preparar / Despachar', $pendientesPreparar)
                ->description('Pedidos pagados listos para empaque')
                ->descriptionIcon('heroicon-m-cube')
                ->color($pendientesPreparar > 0 ? 'warning' : 'success'),

            Stat::make('Pedidos de Hoy', $pedidosHoy)
                ->description('Total de órdenes recibidas hoy')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),

            Stat::make('Ventas Web del Mes', 'S/ '.number_format((float) $ventasMes, 2))
                ->description('Ingresos generados por e-commerce')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Carritos Abandonados', $carritosAbandonados)
                ->description('Carritos con items sin checkout')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('gray'),

            Stat::make('Reclamaciones Pendientes', $reclamacionesPendientes)
                ->description('Libro de reclamaciones sin respuesta')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($reclamacionesPendientes > 0 ? 'danger' : 'success'),
        ];
    }
}
