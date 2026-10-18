<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EstadoCaja;
use App\Enums\TipoMovimientoCaja;
use App\Filament\Resources\CajaSesionResource\Pages;
use App\Models\CajaSesion;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CajaSesionResource extends Resource
{
    protected static ?string $model = CajaSesion::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Caja & Finanzas';

    protected static ?string $navigationLabel = 'Sesiones de Caja';

    protected static ?string $modelLabel = 'Sesión de Caja';

    protected static ?string $pluralModelLabel = 'Sesiones de Caja';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // Las sesiones de caja se abren desde el POS
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Detalles de la Sesión')
                        ->schema([
                            Forms\Components\TextInput::make('id')
                                ->label('ID de Sesión')
                                ->disabled(),

                            Forms\Components\TextInput::make('usuario.name')
                                ->label('Cajero / Vendedor')
                                ->disabled(),

                            Forms\Components\TextInput::make('estado')
                                ->label('Estado')
                                ->formatStateUsing(fn ($state) => $state instanceof EstadoCaja ? $state->etiqueta() : $state)
                                ->disabled(),

                            Forms\Components\TextInput::make('abierta_en')
                                ->label('Abierta el')
                                ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y H:i:s') : '-')
                                ->disabled(),

                            Forms\Components\TextInput::make('cerrada_en')
                                ->label('Cerrada el')
                                ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y H:i:s') : 'En curso')
                                ->placeholder('En curso')
                                ->disabled(),

                            Forms\Components\TextInput::make('observaciones')
                                ->label('Observaciones')
                                ->columnSpanFull()
                                ->disabled(),
                        ])->columns(2),

                    Forms\Components\Section::make('Movimientos Registrados en Caja')
                        ->schema([
                            Forms\Components\Repeater::make('movimientos')
                                ->relationship('movimientos')
                                ->schema([
                                    Forms\Components\TextInput::make('tipo')
                                        ->label('Tipo')
                                        ->formatStateUsing(fn ($state) => $state instanceof TipoMovimientoCaja ? $state->etiqueta() : $state)
                                        ->disabled()
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('concepto')
                                        ->label('Concepto')
                                        ->disabled()
                                        ->columnSpan(5),

                                    Forms\Components\TextInput::make('metodo_pago')
                                        ->label('Método')
                                        ->placeholder('Efectivo')
                                        ->disabled()
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('monto')
                                        ->label('Monto')
                                        ->prefix('S/')
                                        ->disabled()
                                        ->columnSpan(3),
                                ])
                                ->columns(12)
                                ->disableItemCreation()
                                ->disableItemDeletion()
                                ->disableItemMovement(),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make([
                    Forms\Components\Section::make('Arqueo de Caja')
                        ->schema([
                            Forms\Components\TextInput::make('monto_apertura')
                                ->label('Monto Apertura')
                                ->prefix('S/')
                                ->disabled(),

                            Forms\Components\TextInput::make('monto_cierre_esperado')
                                ->label('Esperado en Efectivo')
                                ->prefix('S/')
                                ->placeholder('Calculado al cerrar')
                                ->disabled(),

                            Forms\Components\TextInput::make('monto_cierre_real')
                                ->label('Contado Real en Efectivo')
                                ->prefix('S/')
                                ->placeholder('Ingresado al cerrar')
                                ->disabled(),

                            Forms\Components\TextInput::make('diferencia')
                                ->label('Diferencia de Cuadre')
                                ->prefix('S/')
                                ->placeholder('0.00')
                                ->extraInputAttributes(function (?CajaSesion $record): array {
                                    if (! $record || $record->diferencia === null) {
                                        return [];
                                    }
                                    $dif = (float) $record->diferencia;
                                    if ($dif < 0) {
                                        return ['class' => 'font-bold text-rose-600 bg-rose-50'];
                                    }
                                    if ($dif > 0) {
                                        return ['class' => 'font-bold text-amber-600 bg-amber-50'];
                                    }

                                    return ['class' => 'font-bold text-emerald-600 bg-emerald-50'];
                                })
                                ->disabled(),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('# Sesión')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Cajero / Vendedor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('monto_apertura')
                    ->label('Apertura')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('monto_cierre_esperado')
                    ->label('Esperado')
                    ->money('PEN')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('monto_cierre_real')
                    ->label('Real')
                    ->money('PEN')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('diferencia')
                    ->label('Diferencia')
                    ->money('PEN')
                    ->placeholder('-')
                    ->weight('bold')
                    ->color(function (?CajaSesion $record): string {
                        if (! $record || $record->diferencia === null) {
                            return 'gray';
                        }
                        $dif = (float) $record->diferencia;
                        if ($dif < 0) {
                            return 'danger'; // Resaltado en rojo si hay faltante
                        }
                        if ($dif > 0) {
                            return 'warning';
                        }

                        return 'success';
                    }),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->etiqueta())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\TextColumn::make('abierta_en')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cerrada_en')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('En curso')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options(collect(EstadoCaja::cases())->mapWithKeys(fn ($e) => [$e->value => $e->etiqueta()])),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Vendedor')
                    ->relationship('usuario', 'name'),

                Tables\Filters\Filter::make('con_diferencia')
                    ->label('Solo con Descuadre (Diferencia != 0)')
                    ->query(fn (Builder $query) => $query->whereNotNull('diferencia')->where('diferencia', '!=', 0)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('imprimir_cierre')
                    ->label('Arqueo')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (CajaSesion $record) => route('pos.ticket_caja', $record->id))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCajaSesions::route('/'),
            'view' => Pages\ViewCajaSesion::route('/{record}'),
        ];
    }
}
