<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductoResource\RelationManagers;

use App\Enums\EstadoSerie;
use App\Models\Serie;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SeriesRelationManager extends RelationManager
{
    protected static string $relationship = 'series';

    protected static ?string $title = 'Series Registradas';

    protected static ?string $modelLabel = 'Serie';

    protected static ?string $pluralModelLabel = 'Series';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero_serie')
            ->columns([
                Tables\Columns\TextColumn::make('numero_serie')
                    ->label('N° Serie')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (EstadoSerie $state): string => $state->color())
                    ->formatStateUsing(fn (EstadoSerie $state): string => $state->etiqueta()),
                Tables\Columns\TextColumn::make('costo')
                    ->label('Costo')
                    ->money('PEN')
                    ->visible(fn (): bool => auth()->user()?->can('costos.ver') ?? false),
                Tables\Columns\TextColumn::make('fecha_ingreso')
                    ->label('Fecha Ingreso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('compraDetalle.compra.codigo')
                    ->label('Compra')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        EstadoSerie::DISPONIBLE->value => EstadoSerie::DISPONIBLE->etiqueta(),
                        EstadoSerie::RESERVADA->value => EstadoSerie::RESERVADA->etiqueta(),
                        EstadoSerie::VENDIDA->value => EstadoSerie::VENDIDA->etiqueta(),
                        EstadoSerie::EN_GARANTIA->value => EstadoSerie::EN_GARANTIA->etiqueta(),
                        EstadoSerie::DEVUELTA->value => EstadoSerie::DEVUELTA->etiqueta(),
                        EstadoSerie::BAJA->value => EstadoSerie::BAJA->etiqueta(),
                    ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
