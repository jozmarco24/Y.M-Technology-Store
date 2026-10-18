<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EstadoCompra;
use App\Models\Compra;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UltimasComprasRecibidasWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Últimas 5 Compras Recibidas';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Compra::query()
                    ->where('estado', EstadoCompra::RECIBIDA)
                    ->with(['proveedor', 'recibidor'])
                    ->orderBy('recibido_en', 'desc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('proveedor.razon_social')
                    ->label('Proveedor')
                    ->limit(30),
                Tables\Columns\TextColumn::make('numero_comprobante')
                    ->label('N° Comprobante'),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->visible(fn (): bool => auth()->user()?->can('costos.ver') ?? false),
                Tables\Columns\TextColumn::make('recibidor.name')
                    ->label('Recibido Por'),
                Tables\Columns\TextColumn::make('recibido_en')
                    ->label('Fecha Recepción')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->paginated(false);
    }
}
