<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EstadoGarantia;
use App\Models\Garantia;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class GarantiasPorVencerWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Garantia::query()
                    ->where('estado', EstadoGarantia::VIGENTE->value)
                    ->whereBetween('fecha_fin', [Carbon::today(), Carbon::today()->addDays(30)])
                    ->with(['serie.producto', 'cliente'])
                    ->orderBy('fecha_fin')
            )
            ->heading('⚠️ Garantías por Vencer este Mes (Próximos 30 días)')
            ->columns([
                Tables\Columns\TextColumn::make('serie.numero_serie')
                    ->label('Serie')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('serie.producto.nombre')
                    ->label('Producto')
                    ->limit(35),

                Tables\Columns\TextColumn::make('cliente.nombre_razon_social')
                    ->label('Cliente')
                    ->limit(30),

                Tables\Columns\TextColumn::make('cliente.telefono')
                    ->label('Teléfono')
                    ->placeholder('Sin teléfono'),

                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Fecha Vencimiento')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('dias_restantes')
                    ->label('Días Restantes')
                    ->state(fn (Garantia $record): int => $record->diasRestantes())
                    ->badge()
                    ->color('warning')
                    ->suffix(' días'),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver Garantía')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Garantia $record): string => route('filament.admin.resources.garantias.view', $record)),
            ]);
    }
}
