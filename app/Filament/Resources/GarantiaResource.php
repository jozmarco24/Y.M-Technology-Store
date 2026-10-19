<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EstadoGarantia;
use App\Filament\Resources\GarantiaResource\Pages;
use App\Models\Garantia;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class GarantiaResource extends Resource
{
    protected static ?string $model = Garantia::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Garantías & Postventa';

    protected static ?string $navigationLabel = 'Garantías Emitidas';

    protected static ?string $modelLabel = 'Garantía';

    protected static ?string $pluralModelLabel = 'Garantías';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false; // Se generan automáticamente al registrar ventas en POS
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user && ($user->hasRole('admin') || $user->can('garantias.ver'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalle de Garantía')
                    ->schema([
                        Forms\Components\TextInput::make('serie.numero_serie')
                            ->label('Número de Serie')
                            ->disabled(),

                        Forms\Components\TextInput::make('serie.producto.nombre')
                            ->label('Producto')
                            ->disabled(),

                        Forms\Components\TextInput::make('cliente.nombre_razon_social')
                            ->label('Cliente')
                            ->disabled(),

                        Forms\Components\TextInput::make('estado')
                            ->label('Estado')
                            ->formatStateUsing(fn ($state) => $state instanceof EstadoGarantia ? $state->etiqueta() : $state)
                            ->disabled(),

                        Forms\Components\DatePicker::make('fecha_inicio')
                            ->label('Fecha de Inicio / Venta')
                            ->disabled(),

                        Forms\Components\DatePicker::make('fecha_fin')
                            ->label('Vencimiento de Garantía')
                            ->disabled(),

                        Forms\Components\DatePicker::make('dias_cambio_hasta')
                            ->label('Plazo para Cambio Regular')
                            ->disabled(),

                        Forms\Components\Textarea::make('condiciones_snapshot')
                            ->label('Condiciones pactadas al momento de la venta')
                            ->rows(4)
                            ->columnSpanFull()
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('serie.numero_serie')
                    ->label('Serie')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('serie.producto.nombre')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->limit(35),

                Tables\Columns\TextColumn::make('cliente.nombre_razon_social')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('dias_restantes')
                    ->label('Días Restantes')
                    ->state(fn (Garantia $record): int => $record->diasRestantes())
                    ->alignCenter()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('fecha_fin', $direction))
                    ->badge()
                    ->color(function (Garantia $record): string {
                        if ($record->estado !== EstadoGarantia::VIGENTE) {
                            return 'gray';
                        }
                        $dias = $record->diasRestantes();
                        if ($dias <= 0) {
                            return 'danger';
                        }
                        if ($dias <= 30) {
                            return 'warning';
                        }

                        return 'success';
                    }),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(function (Garantia $record): string {
                        if ($record->estado === EstadoGarantia::VIGENTE && $record->diasRestantes() <= 30 && $record->diasRestantes() > 0) {
                            return 'warning'; // Por vencer en 30 días
                        }

                        return $record->estado->color();
                    })
                    ->formatStateUsing(function (Garantia $record): string {
                        if ($record->estado === EstadoGarantia::VIGENTE && $record->diasRestantes() <= 30 && $record->diasRestantes() > 0) {
                            return 'Por vencer ('.$record->diasRestantes().' d)';
                        }

                        return $record->estado->etiqueta();
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado de Garantía')
                    ->options(collect(EstadoGarantia::cases())->mapWithKeys(fn ($e) => [$e->value => $e->etiqueta()])),

                Tables\Filters\Filter::make('por_vencer')
                    ->label('Por Vencer (Próximos 30 días)')
                    ->query(fn (Builder $query) => $query
                        ->where('estado', EstadoGarantia::VIGENTE->value)
                        ->whereBetween('fecha_fin', [Carbon::today(), Carbon::today()->addDays(30)])),

                Tables\Filters\SelectFilter::make('producto_id')
                    ->label('Producto')
                    ->relationship('serie.producto', 'nombre')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'nombre_razon_social')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGarantias::route('/'),
            'view' => Pages\ViewGarantia::route('/{record}'),
        ];
    }
}
