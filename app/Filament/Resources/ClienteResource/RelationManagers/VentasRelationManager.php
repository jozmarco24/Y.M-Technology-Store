<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use App\Models\Venta;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VentasRelationManager extends RelationManager
{
    protected static string $relationship = 'ventas';

    protected static ?string $title = 'Historial de Compras del Cliente';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('codigo')
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tipo_comprobante')
                    ->label('Comprobante')
                    ->formatStateUsing(fn ($state) => $state->etiqueta())
                    ->badge(),

                Tables\Columns\TextColumn::make('comprobante_completo')
                    ->label('Serie - Número')
                    ->state(fn (Venta $record) => "{$record->serie_comprobante}-{$record->numero_comprobante}"),

                Tables\Columns\TextColumn::make('vendedor.name')
                    ->label('Vendedor')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->formatStateUsing(fn ($state) => $state->etiqueta())
                    ->color(fn ($state) => $state->color())
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('ticket')
                    ->label('Ticket')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Venta $record) => route('pos.ticket', $record->id))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }
}
