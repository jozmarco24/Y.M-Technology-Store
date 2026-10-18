<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EstadoSerie;
use App\Models\Producto;
use App\Models\Serie;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsInventarioOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $productosActivos = Producto::where('activo', true)->count();
        $seriesDisponibles = Serie::where('estado', EstadoSerie::DISPONIBLE)->count();

        $stats = [
            Stat::make('Productos Activos', (string) $productosActivos)
                ->description('Total en catálogo habilitado')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make('Series Disponibles', (string) $seriesDisponibles)
                ->description('Listas para la venta')
                ->descriptionIcon('heroicon-m-qr-code')
                ->color('primary'),
        ];

        // Valor de inventario: Solo visible para usuarios con permiso 'costos.ver' (Admin)
        if (auth()->user()?->can('costos.ver')) {
            $valorInventario = (float) Serie::where('estado', EstadoSerie::DISPONIBLE)->sum('costo');
            $stats[] = Stat::make('Valor del Inventario', 'S/ ' . number_format($valorInventario, 2))
                ->description('Costo total de series en stock')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning');
        }

        return $stats;
    }
}
