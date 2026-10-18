<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Producto;
use App\Services\InventarioService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ProductosBajoStockWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Productos con Stock Bajo o Crítico';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Producto::query()
                    ->where('activo', true)
                    ->where(function (Builder $query) {
                        $query->where('controla_series', true)
                            ->whereRaw('(SELECT COUNT(*) FROM series WHERE series.producto_id = productos.id AND series.estado = \'disponible\') <= productos.stock_minimo')
                            ->orWhere(function ($q2) {
                                $q2->where('controla_series', false)
                                    ->whereRaw('(SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_inventario WHERE movimientos_inventario.producto_id = productos.id) <= productos.stock_minimo');
                            });
                    })
                    ->with(['categoria', 'marca'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->badge(),
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Producto')
                    ->limit(35),
                Tables\Columns\TextColumn::make('categoria.nombre')
                    ->label('Categoría'),
                Tables\Columns\TextColumn::make('marca.nombre')
                    ->label('Marca'),
                Tables\Columns\TextColumn::make('stock_minimo')
                    ->label('Mínimo')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('stock_actual')
                    ->label('Stock Actual')
                    ->state(fn (Producto $record): int => app(InventarioService::class)->stockDisponible($record))
                    ->badge()
                    ->color('danger'),
            ])
            ->paginated([5, 10]);
    }
}
