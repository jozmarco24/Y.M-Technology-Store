<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EstadoCompra;
use App\Enums\TipoComprobante;
use App\Filament\Resources\CompraResource\Pages;
use App\Models\Compra;
use App\Models\Producto;
use App\Services\CompraService;
use App\Services\InventarioService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class CompraResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Compras e Inventario';

    protected static ?string $modelLabel = 'Compra';

    protected static ?string $pluralModelLabel = 'Compras';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Comprobante')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Generado automáticamente'),
                        Forms\Components\Select::make('proveedor_id')
                            ->label('Proveedor')
                            ->relationship('proveedor', 'razon_social')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('tipo_comprobante')
                            ->label('Tipo de Comprobante')
                            ->options([
                                TipoComprobante::FACTURA->value => TipoComprobante::FACTURA->etiqueta(),
                                TipoComprobante::BOLETA->value => TipoComprobante::BOLETA->etiqueta(),
                                TipoComprobante::GUIA->value => TipoComprobante::GUIA->etiqueta(),
                                TipoComprobante::OTRO->value => TipoComprobante::OTRO->etiqueta(),
                            ])
                            ->required()
                            ->default(TipoComprobante::FACTURA->value),
                        Forms\Components\TextInput::make('numero_comprobante')
                            ->label('Número de Comprobante')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\DatePicker::make('fecha_emision')
                            ->label('Fecha de Emisión')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('moneda')
                            ->label('Moneda')
                            ->options([
                                'PEN' => 'Soles (PEN)',
                                'USD' => 'Dólares (USD)',
                            ])
                            ->default('PEN')
                            ->required(),
                        Forms\Components\TextInput::make('tipo_cambio')
                            ->label('Tipo de Cambio')
                            ->numeric()
                            ->default(1.0000),
                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Detalle de Productos')
                    ->schema([
                        Forms\Components\Repeater::make('detalles')
                            ->relationship('detalles')
                            ->schema([
                                Forms\Components\Select::make('producto_id')
                                    ->label('Producto')
                                    ->options(Producto::query()->pluck('nombre', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(4),
                                Forms\Components\TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        $cant = (int) $get('cantidad');
                                        $costo = (float) $get('costo_unitario');
                                        $set('subtotal', number_format($cant * $costo, 2, '.', ''));
                                    })
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('costo_unitario')
                                    ->label('Costo Unitario')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->default(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->visible(fn (): bool => auth()->user()?->can('costos.ver') ?? false)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        $cant = (int) $get('cantidad');
                                        $costo = (float) $get('costo_unitario');
                                        $set('subtotal', number_format($cant * $costo, 2, '.', ''));
                                    })
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(3),
                            ])
                            ->columns(12)
                            ->minItems(1)
                            ->required(),
                    ]),

                Forms\Components\Section::make('Totales')
                    ->schema([
                        Forms\Components\Placeholder::make('subtotal_preview')
                            ->label('Subtotal (Sin IGV)')
                            ->content(fn (?Compra $record): string => 'S/ '.number_format((float) ($record?->subtotal ?? 0), 2))
                            ->visible(fn (): bool => auth()->user()?->can('costos.ver') ?? false),
                        Forms\Components\Placeholder::make('igv_preview')
                            ->label('IGV (18%)')
                            ->content(fn (?Compra $record): string => 'S/ '.number_format((float) ($record?->igv ?? 0), 2))
                            ->visible(fn (): bool => auth()->user()?->can('costos.ver') ?? false),
                        Forms\Components\Placeholder::make('total_preview')
                            ->label('Total')
                            ->content(fn (?Compra $record): string => 'S/ '.number_format((float) ($record?->total ?? 0), 2))
                            ->visible(fn (): bool => auth()->user()?->can('costos.ver') ?? false),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('proveedor.razon_social')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tipo_comprobante')
                    ->label('Comprobante')
                    ->badge()
                    ->formatStateUsing(fn (TipoComprobante $state): string => $state->etiqueta()),
                Tables\Columns\TextColumn::make('numero_comprobante')
                    ->label('N° Comprobante')
                    ->searchable(),
                Tables\Columns\TextColumn::make('fecha_emision')
                    ->label('Emisión')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->can('costos.ver') ?? false),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (EstadoCompra $state): string => $state->color())
                    ->formatStateUsing(fn (EstadoCompra $state): string => $state->etiqueta()),
                Tables\Columns\TextColumn::make('recibido_en')
                    ->label('Recibido')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        EstadoCompra::BORRADOR->value => EstadoCompra::BORRADOR->etiqueta(),
                        EstadoCompra::RECIBIDA->value => EstadoCompra::RECIBIDA->etiqueta(),
                        EstadoCompra::ANULADA->value => EstadoCompra::ANULADA->etiqueta(),
                    ]),
                Tables\Filters\SelectFilter::make('proveedor_id')
                    ->label('Proveedor')
                    ->relationship('proveedor', 'razon_social'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Compra $record): bool => $record->estado === EstadoCompra::BORRADOR),

                // Acción: Recibir mercadería (Solo en borrador)
                Tables\Actions\Action::make('recibir')
                    ->label('Recibir mercadería')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->visible(fn (Compra $record): bool => $record->estado === EstadoCompra::BORRADOR && (auth()->user()?->can('compras.recibir') ?? false))
                    ->form(function (Compra $record): array {
                        $fields = [];
                        $record->load(['detalles.producto']);

                        foreach ($record->detalles as $detalle) {
                            if ($detalle->producto->controla_series) {
                                $fields[] = Forms\Components\Textarea::make("series_{$detalle->id}")
                                    ->label("Series para: {$detalle->producto->nombre} ({$detalle->cantidad} unidades requeridas)")
                                    ->helperText("Pegue o escanee una serie por línea. Se esperan exactamente {$detalle->cantidad} series.")
                                    ->rows(6)
                                    ->required();
                            } else {
                                $fields[] = Forms\Components\Placeholder::make("info_{$detalle->id}")
                                    ->label("Producto: {$detalle->producto->nombre}")
                                    ->content("Se ingresarán {$detalle->cantidad} unidades directamente al inventario general (sin control de series).");
                            }
                        }

                        return $fields;
                    })
                    ->action(function (Compra $record, array $data, InventarioService $service): void {
                        try {
                            $seriesPorDetalle = [];
                            foreach ($record->detalles as $detalle) {
                                if ($detalle->producto->controla_series) {
                                    $texto = (string) ($data["series_{$detalle->id}"] ?? '');
                                    $lineas = preg_split("/\r\n|\n|\r/", trim($texto));
                                    $seriesLimpia = array_values(array_filter(array_map('trim', $lineas ?: [])));
                                    $seriesPorDetalle[$detalle->id] = $seriesLimpia;
                                }
                            }

                            $service->recibirCompra($record, $seriesPorDetalle, auth()->user());

                            Notification::make()
                                ->title('Mercadería recibida exitosamente')
                                ->body("Se registraron las series y movimientos para la compra {$record->codigo}.")
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Error al recibir mercadería')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Acción: Anular (Con motivo obligatorio y confirmación)
                Tables\Actions\Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Compra $record): bool => $record->estado !== EstadoCompra::ANULADA && (auth()->user()?->can('compras.anular') ?? false))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('motivo')
                            ->label('Motivo de anulación')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Compra $record, array $data, CompraService $service): void {
                        try {
                            $service->anular($record, (string) $data['motivo'], auth()->user());

                            Notification::make()
                                ->title('Compra anulada')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('No se pudo anular la compra')
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
            'index' => Pages\ListCompras::route('/'),
            'create' => Pages\CreateCompra::route('/create'),
            'edit' => Pages\EditCompra::route('/{record}/edit'),
        ];
    }
}
