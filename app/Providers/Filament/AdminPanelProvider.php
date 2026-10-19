<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\ComprobantesAlertWidget;
use App\Filament\Widgets\ProductosBajoStockWidget;
use App\Filament\Widgets\ReportesDashboardStatsWidget;
use App\Filament\Widgets\StatsInventarioOverview;
use App\Filament\Widgets\StatsVentasOverview;
use App\Filament\Widgets\TopProductosVendidosWidget;
use App\Filament\Widgets\UltimasComprasRecibidasWidget;
use App\Filament\Widgets\VentasUltimos30DiasChart;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                ComprobantesAlertWidget::class,
                ReportesDashboardStatsWidget::class,
                StatsVentasOverview::class,
                StatsInventarioOverview::class,
                VentasUltimos30DiasChart::class,
                TopProductosVendidosWidget::class,
                ProductosBajoStockWidget::class,
                UltimasComprasRecibidasWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
