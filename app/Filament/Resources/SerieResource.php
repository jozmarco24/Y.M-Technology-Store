<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EstadoSerie;
use App\Filament\Resources\SerieResource\Pages;
use App\Models\Serie;
use App\Services\InventarioService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class SerieResource extends Resource
{
    protected static ?string $model = Serie::class;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Compras e Inventario';

    protected static ?string $modelLabel = 'Serie';

    protected static ?string $pluralModelLabel = 'Series y Trazabilidad';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('numero_serie')
                ->label('Número de Serie')
                ->disabled(),
            Forms\Components\TextInput::make('estado')
                ->label('Estado')
                ->disabled(),
            Forms\Components\TextInput::make('costo')
                ->label('Costo (PEN)')
                ->disabled()
                ->visible(fn (): bool => auth()->user()?->can('costos.ver') ?? false),
            Forms\Components\Textarea::make('observaciones')
                ->label('Observaciones')
                ->disabled()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero_serie')
                    ->label('N° de Serie')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->limit(35),
                Tables\Columns\TextColumn::make('producto.categoria.nombre')
                    ->label('Categoría')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (EstadoSerie $state): string => $state->color())
                    ->formatStateUsing(fn (EstadoSerie $state): string => $state->etiqueta()),
                Tables\Columns\TextColumn::make('costo')
                    ->label('Costo')
                    ->money('PEN')
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->can('costos.ver') ?? false),
                Tables\Columns\TextColumn::make('fecha_ingreso')
                    ->label('Fecha Ingreso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('compraDetalle.compra.codigo')
                    ->label('Compra Origen')
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
                Tables\Filters\SelectFilter::make('producto_id')
                    ->label('Producto')
                    ->relationship('producto', 'nombre')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('categoria')
                    ->label('Categoría')
                    ->relationship('producto.categoria', 'nombre'),
            ])
            ->actions([
                // Ver historial en modal
                Tables\Actions\Action::make('historial')
                    ->label('Historial')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->modalHeading(fn (Serie $record): string => "Historial de Serie: {$record->numero_serie}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->infolist(function (Serie $record, InventarioService $service): array {
                        $movimientos = $service->historialSerie($record);

                        return [
                            Section::make('Movimientos Registrados')
                                ->schema([
                                    RepeatableEntry::make('movimientos')
                                        ->state($movimientos->toArray())
                                        ->schema([
                                            TextEntry::make('tipo')
                                                ->label('Tipo')
                                                ->badge(),
                                            TextEntry::make('motivo')
                                                ->label('Motivo'),
                                            TextEntry::make('stock_resultante')
                                                ->label('Stock Resultante'),
                                            TextEntry::make('created_at')
                                                ->label('Fecha')
                                                ->dateTime('d/m/Y H:i'),
                                        ])->columns(4),
                                ]),
                        ];
                    }),

                // Dar de baja
                Tables\Actions\Action::make('dar_de_baja')
                    ->label('Dar de baja')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
                    ->visible(fn (Serie $record): bool => $record->estado === EstadoSerie::DISPONIBLE && (auth()->user()?->can('series.dar_baja') ?? false))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('motivo')
                            ->label('Motivo de la baja')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Serie $record, array $data, InventarioService $service): void {
                        try {
                            $service->darDeBaja($record, (string) $data['motivo'], auth()->user());
                            Notification::make()
                                ->title('Serie dada de baja')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Error al dar de baja la serie')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Ajustar estado (Solo Admin)
                Tables\Actions\Action::make('ajustar_estado')
                    ->label('Ajustar Estado')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false)
                    ->form(fn (Serie $record): array => [
                        Forms\Components\Select::make('nuevo_estado')
                            ->label('Nuevo Estado')
                            ->options(
                                collect(EstadoSerie::cases())
                                    ->filter(fn (EstadoSerie $e): bool => $record->estado->puedePasarA($e))
                                    ->mapWithKeys(fn (EstadoSerie $e): array => [$e->value => $e->etiqueta()])
                                    ->toArray()
                            )
                            ->required(),
                        Forms\Components\Textarea::make('motivo')
                            ->label('Motivo del ajuste administrativo')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Serie $record, array $data, InventarioService $service): void {
                        try {
                            $nuevoEstado = EstadoSerie::from($data['nuevo_estado']);
                            $service->ajustarEstado($record, $nuevoEstado, (string) $data['motivo'], auth()->user());

                            Notification::make()
                                ->title('Estado ajustado exitosamente')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Error al ajustar estado')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSeries::route('/'),
        ];
    }
}
