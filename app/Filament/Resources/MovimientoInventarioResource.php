<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\TipoMovimiento;
use App\Filament\Resources\MovimientoInventarioResource\Pages;
use App\Models\MovimientoInventario;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MovimientoInventarioResource extends Resource
{
    protected static ?string $model = MovimientoInventario::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Compras e Inventario';

    protected static ?string $modelLabel = 'Movimiento de Inventario';

    protected static ?string $pluralModelLabel = 'Kárdex / Movimientos';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->limit(35),
                Tables\Columns\TextColumn::make('serie.numero_serie')
                    ->label('N° Serie')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (TipoMovimiento $state): string => $state->color())
                    ->formatStateUsing(fn (TipoMovimiento $state): string => $state->etiqueta()),
                Tables\Columns\TextColumn::make('cantidad')
                    ->label('Cant.')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "+{$state}" : (string) $state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('stock_resultante')
                    ->label('Stock Resultante')
                    ->badge(),
                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo / Glosa')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Responsable')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo de Movimiento')
                    ->options([
                        TipoMovimiento::INGRESO->value => TipoMovimiento::INGRESO->etiqueta(),
                        TipoMovimiento::SALIDA->value => TipoMovimiento::SALIDA->etiqueta(),
                        TipoMovimiento::AJUSTE->value => TipoMovimiento::AJUSTE->etiqueta(),
                        TipoMovimiento::ANULACION->value => TipoMovimiento::ANULACION->etiqueta(),
                    ]),
                Tables\Filters\SelectFilter::make('producto_id')
                    ->label('Producto')
                    ->relationship('producto', 'nombre')
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['hasta'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMovimientoInventarios::route('/'),
        ];
    }
}
