<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EstadoComprobante;
use App\Models\ComprobanteElectronico;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ComprobantesAlertWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return Auth::user()?->hasRole('admin') ?? false;
    }

    protected function getStats(): array
    {
        $pendientes = ComprobanteElectronico::where('estado', EstadoComprobante::PENDIENTE)->count();
        $errores = ComprobanteElectronico::where('estado', EstadoComprobante::ERROR)->count();
        $rechazados = ComprobanteElectronico::where('estado', EstadoComprobante::RECHAZADO)->count();

        $rechazadoStat = Stat::make('SUNAT Rechazados', (string) $rechazados)
            ->description($rechazados > 0 ? '¡Atención! Comprobantes rechazados por SUNAT' : 'Sin comprobantes rechazados')
            ->descriptionIcon($rechazados > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge')
            ->color($rechazados > 0 ? 'danger' : 'success');

        if ($rechazados > 0) {
            $rechazadoStat->extraAttributes([
                'class' => 'ring-2 ring-rose-500 bg-rose-50 dark:bg-rose-950/20',
            ]);
        }

        return [
            Stat::make('SUNAT Pendientes', (string) $pendientes)
                ->description('En cola o pendientes de envío')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendientes > 0 ? 'warning' : 'gray'),

            Stat::make('SUNAT con Error', (string) $errores)
                ->description('En proceso de reintento automático')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($errores > 0 ? 'warning' : 'gray'),

            $rechazadoStat,
        ];
    }
}
