<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Producto;
use App\Services\ReporteVentaService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopProductosVendidosWidget extends BaseWidget
{
    protected static ?string $heading = 'Top 5 Productos Más Vendidos del Mes';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        $service = app(ReporteVentaService::class);
        $inicioMes = now()->startOfMonth();
        $topProductos = $service->productosMasVendidos(5, $inicioMes, now());

        return $table
            ->query(
                Producto::query()->whereIn('id', array_column($topProductos, 'producto_id'))
            )
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Producto')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('precio_venta')
                    ->label('Precio Actual')
                    ->money('PEN'),
            ])
            ->paginated(false);
    }
}
