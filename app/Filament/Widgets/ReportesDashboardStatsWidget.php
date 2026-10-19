<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\Reportes\InventarioPage;
use App\Filament\Pages\Reportes\RentabilidadPage;
use App\Filament\Pages\Reportes\ResumenEjecutivoPage;
use App\Models\User;
use App\Services\Reportes\InventarioReporteService;
use App\Services\Reportes\RentabilidadService;
use App\Services\Reportes\ResumenEjecutivoService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ReportesDashboardStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user && ($user->hasRole('admin') || $user->hasPermissionTo('reportes.ver_inventario'));
    }

    protected function getStats(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();
        $esAdmin = $usuario->hasRole('admin') || $usuario->hasPermissionTo('reportes.ver_margen');

        /** @var InventarioReporteService $invService */
        $invService = app(InventarioReporteService::class);
        $stockMuertoList = $invService->stockMuerto($usuario);
        $reposicionesList = $invService->sugerenciaReposicion($usuario);

        $stats = [];

        // Margen del Mes (solo admin)
        if ($esAdmin) {
            /** @var RentabilidadService $rentService */
            $rentService = app(RentabilidadService::class);
            $margenMes = $rentService->margenPorProducto($usuario, now()->startOfMonth(), now(), null, 1000);
            $totalMargen = (float) collect($margenMes)->sum('margen_monto');

            $stats[] = Stat::make('Margen del Mes', 'S/ '.number_format($totalMargen, 2))
                ->description('Rentabilidad bruta acumulada')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success')
                ->url(RentabilidadPage::getUrl());
        }

        // Capital Inmovilizado en Stock Muerto
        $capitalInmovilizado = (float) collect($stockMuertoList)->sum('capital_inmovilizado');
        $statStockMuerto = Stat::make('Stock Muerto', count($stockMuertoList).' productos');
        if ($esAdmin) {
            $statStockMuerto->description('S/ '.number_format($capitalInmovilizado, 2).' inmovilizado');
        } else {
            $statStockMuerto->description('Sin rotación en > 90 días');
        }
        $statStockMuerto->descriptionIcon('heroicon-m-clock')
            ->color(count($stockMuertoList) > 0 ? 'danger' : 'gray')
            ->url(InventarioPage::getUrl());
        $stats[] = $statStockMuerto;

        // Reposiciones Sugeridas
        $stats[] = Stat::make('Reposición Sugerida', count($reposicionesList).' productos')
            ->description('Requieren compra para cubrir 30 días')
            ->descriptionIcon('heroicon-m-arrow-path')
            ->color(count($reposicionesList) > 0 ? 'warning' : 'success')
            ->url(InventarioPage::getUrl());

        // Alertas Activas (solo admin)
        if ($esAdmin) {
            /** @var ResumenEjecutivoService $resumenService */
            $resumenService = app(ResumenEjecutivoService::class);
            $resumen = $resumenService->generar($usuario, now()->subDays(7), now());
            $alertasCount = count($resumen['alertas'] ?? []);

            $stats[] = Stat::make('Alertas Activas', $alertasCount.' riesgos detectados')
                ->description('Resumen ejecutivo semanal')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($alertasCount > 0 ? 'danger' : 'success')
                ->url(ResumenEjecutivoPage::getUrl());
        }

        return $stats;
    }
}
