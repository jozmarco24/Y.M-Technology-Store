<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EstadoComprobante;
use App\Enums\EstadoVenta;
use App\Enums\MetodoPago;
use App\Enums\TipoComprobanteVenta;
use App\Filament\Resources\VentaResource\Pages;
use App\Jobs\EnviarComprobanteJob;
use App\Models\User;
use App\Models\Venta;
use App\Services\Facturacion\FacturacionService;
use App\Services\VentaService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class VentaResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Ventas & Clientes';

    protected static ?string $navigationLabel = 'Ventas Realizadas';

    protected static ?string $modelLabel = 'Venta';

    protected static ?string $pluralModelLabel = 'Ventas';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // Las ventas se registran exclusivamente desde el POS
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Información del Comprobante')
                        ->schema([
                            Forms\Components\TextInput::make('codigo')
                                ->label('Código')
                                ->disabled(),

                            Forms\Components\TextInput::make('tipo_comprobante')
                                ->label('Tipo de Comprobante')
                                ->formatStateUsing(fn ($state) => $state instanceof TipoComprobanteVenta ? $state->etiqueta() : $state)
                                ->disabled(),

                            Forms\Components\TextInput::make('serie_comprobante')
                                ->label('Serie')
                                ->disabled(),

                            Forms\Components\TextInput::make('numero_comprobante')
                                ->label('Número')
                                ->disabled(),

                            Forms\Components\TextInput::make('cliente.nombre_razon_social')
                                ->label('Cliente')
                                ->disabled(),

                            Forms\Components\TextInput::make('vendedor.name')
                                ->label('Vendedor')
                                ->disabled(),

                            Forms\Components\TextInput::make('created_at')
                                ->label('Fecha y Hora')
                                ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y H:i:s') : '-')
                                ->disabled(),

                            Forms\Components\TextInput::make('estado')
                                ->label('Estado')
                                ->formatStateUsing(fn ($state) => $state instanceof EstadoVenta ? $state->etiqueta() : $state)
                                ->disabled(),
                        ])->columns(2),

                    Forms\Components\Section::make('Ítems Vendidos')
                        ->schema([
                            Forms\Components\Repeater::make('detalles')
                                ->relationship('detalles')
                                ->schema([
                                    Forms\Components\TextInput::make('descripcion')
                                        ->label('Producto')
                                        ->disabled()
                                        ->columnSpan(3),

                                    Forms\Components\TextInput::make('cantidad')
                                        ->label('Cant.')
                                        ->disabled()
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make('precio_unitario')
                                        ->label('Precio Unit.')
                                        ->prefix('S/')
                                        ->disabled()
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('descuento')
                                        ->label('Dscto.')
                                        ->prefix('S/')
                                        ->disabled()
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('subtotal')
                                        ->label('Subtotal')
                                        ->prefix('S/')
                                        ->disabled()
                                        ->columnSpan(2),

                                    // Costo y margen SOLO visibles para administradores
                                    Forms\Components\TextInput::make('costo_unitario')
                                        ->label('Costo Unit.')
                                        ->prefix('S/')
                                        ->visible(fn () => Auth::user()?->hasRole('admin'))
                                        ->disabled()
                                        ->columnSpan(2),
                                ])
                                ->columns(12)
                                ->disableItemCreation()
                                ->disableItemDeletion()
                                ->disableItemMovement(),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make([
                    Forms\Components\Section::make('Totales de la Operación')
                        ->schema([
                            Forms\Components\TextInput::make('subtotal')
                                ->label('Subtotal')
                                ->prefix('S/')
                                ->disabled(),

                            Forms\Components\TextInput::make('descuento')
                                ->label('Descuento')
                                ->prefix('S/')
                                ->disabled(),

                            Forms\Components\TextInput::make('igv')
                                ->label('I.G.V. (18%)')
                                ->prefix('S/')
                                ->disabled(),

                            Forms\Components\TextInput::make('total')
                                ->label('Total Cobrado')
                                ->prefix('S/')
                                ->extraInputAttributes(['class' => 'font-bold text-lg text-primary-600'])
                                ->disabled(),

                            // Margen total (solo admin)
                            Forms\Components\Placeholder::make('margen_calculado')
                                ->label('Margen Bruto (Admin)')
                                ->visible(fn () => Auth::user()?->hasRole('admin'))
                                ->content(function (?Venta $record): string {
                                    if (! $record) {
                                        return '-';
                                    }
                                    $costoTotal = (float) $record->detalles->sum(fn ($d) => ((float) $d->costo_unitario) * ((int) $d->cantidad));
                                    $totalVenta = (float) $record->total;
                                    $margen = $totalVenta - $costoTotal;
                                    $porcentaje = $totalVenta > 0 ? round(($margen / $totalVenta) * 100, 1) : 0;

                                    return 'S/ '.number_format($margen, 2)." ({$porcentaje}%)";
                                }),
                        ]),

                    Forms\Components\Section::make('Pagos Registrados')
                        ->schema([
                            Forms\Components\Repeater::make('pagos')
                                ->relationship('pagos')
                                ->schema([
                                    Forms\Components\TextInput::make('metodo')
                                        ->label('Método')
                                        ->formatStateUsing(fn ($state) => $state instanceof MetodoPago ? $state->etiqueta() : $state)
                                        ->disabled(),

                                    Forms\Components\TextInput::make('monto')
                                        ->label('Monto')
                                        ->prefix('S/')
                                        ->disabled(),

                                    Forms\Components\TextInput::make('referencia')
                                        ->label('Referencia')
                                        ->placeholder('-')
                                        ->disabled(),
                                ])
                                ->disableItemCreation()
                                ->disableItemDeletion()
                                ->disableItemMovement(),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->state(fn (Venta $record) => "{$record->tipo_comprobante->etiqueta()} {$record->serie_comprobante}-{$record->numero_comprobante}")
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('serie_comprobante', 'ilike', "%{$search}%")
                            ->orWhere('numero_comprobante', 'like', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('cliente.nombre_razon_social')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

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
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->etiqueta())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\TextColumn::make('comprobanteElectronico.estado')
                    ->label('SUNAT')
                    ->badge()
                    ->placeholder('-')
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'aceptado' => 'success',
                        'rechazado' => 'danger',
                        'error' => 'danger',
                        'enviando' => 'info',
                        'pendiente' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state ? strtoupper($state instanceof EstadoComprobante ? $state->value : (string) $state) : '-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_comprobante')
                    ->label('Tipo de Comprobante')
                    ->options(collect(TipoComprobanteVenta::cases())->mapWithKeys(fn ($t) => [$t->value => $t->etiqueta()])),

                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(collect(EstadoVenta::cases())->mapWithKeys(fn ($e) => [$e->value => $e->etiqueta()])),

                Tables\Filters\SelectFilter::make('vendedor_id')
                    ->label('Vendedor')
                    ->relationship('vendedor', 'name'),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('desde')->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['hasta'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('ticket')
                    ->label('Ticket')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Venta $record) => route('pos.ticket', $record->id))
                    ->openUrlInNewTab(),

                // Enlaces de descarga electrónica (PDF / XML / CDR) visibles solo con permiso ventas.ver
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->visible(fn (Venta $record) => Auth::user()?->can('ventas.ver') && ! empty($record->comprobanteElectronico?->enlace_pdf))
                    ->url(fn (Venta $record) => $record->comprobanteElectronico?->enlace_pdf)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('xml')
                    ->label('XML')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->visible(fn (Venta $record) => Auth::user()?->can('ventas.ver') && ! empty($record->comprobanteElectronico?->enlace_xml))
                    ->url(fn (Venta $record) => $record->comprobanteElectronico?->enlace_xml)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('cdr')
                    ->label('CDR')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Venta $record) => Auth::user()?->can('ventas.ver') && ! empty($record->comprobanteElectronico?->enlace_cdr))
                    ->url(fn (Venta $record) => $record->comprobanteElectronico?->enlace_cdr)
                    ->openUrlInNewTab(),

                // Reintentar envío a SUNAT
                Tables\Actions\Action::make('reintentar_envio')
                    ->label('Reintentar Envío')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Venta $record) => Auth::user()?->hasRole('admin') && $record->comprobanteElectronico !== null && ! $record->comprobanteElectronico->estaAceptado())
                    ->requiresConfirmation()
                    ->modalHeading('Reintentar Envío a SUNAT')
                    ->action(function (Venta $record) {
                        activity('ventas')
                            ->performedOn($record)
                            ->causedBy(Auth::user())
                            ->log('Reintento manual de envío de comprobante a SUNAT');

                        EnviarComprobanteJob::dispatch($record->id)
                            ->onQueue(config('facturacion.cola', 'facturacion'));

                        Notification::make()
                            ->title('Job de envío despachado')
                            ->body("Se despachó el envío del comprobante de la venta {$record->codigo}.")
                            ->success()
                            ->send();
                    }),

                // Consultar estado en Nubefact
                Tables\Actions\Action::make('consultar_sunat')
                    ->label('Consultar Estado')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->visible(fn (Venta $record) => Auth::user()?->hasRole('admin') && $record->comprobanteElectronico !== null)
                    ->action(function (Venta $record, FacturacionService $facturacionService) {
                        try {
                            $resultado = $facturacionService->consultar($record->comprobanteElectronico);
                            Notification::make()
                                ->title('Consulta SUNAT completada')
                                ->body("Estado actual: {$resultado->estado->value}. Mensaje: ".($resultado->mensajeSunat ?? 'Sin mensaje'))
                                ->info()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error en consulta')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Ver respuesta cruda de SUNAT
                Tables\Actions\Action::make('ver_respuesta_sunat')
                    ->label('Respuesta SUNAT')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('gray')
                    ->visible(fn (Venta $record) => Auth::user()?->hasRole('admin') && $record->comprobanteElectronico?->respuesta_json !== null)
                    ->modalHeading('Respuesta de SUNAT / Nubefact')
                    ->modalContent(fn (Venta $record) => view('filament.components.respuesta-sunat-modal', [
                        'comprobante' => $record->comprobanteElectronico,
                    ]))
                    ->modalSubmitAction(false),

                // Acción ANULAR: solo admin con motivo obligatorio y confirmación
                Tables\Actions\Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Venta $record) => Auth::user()?->hasRole('admin') && $record->esCompletada())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Venta $record) => "Anular Venta {$record->codigo}")
                    ->modalDescription('Esta acción devolverá las series al stock disponible, registrará el movimiento de anulación en inventario y revertirá los ingresos en caja.')
                    ->form([
                        Forms\Components\Textarea::make('motivo_anulacion')
                            ->label('Motivo de Anulación')
                            ->placeholder('Ingrese el motivo detallado de la anulación...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Venta $record, array $data, VentaService $ventaService): void {
                        try {
                            /** @var User $user */
                            $user = Auth::user();
                            $ventaService->anular($record, (string) $data['motivo_anulacion'], $user);

                            Notification::make()
                                ->title('Venta Anulada')
                                ->body("La venta {$record->codigo} ha sido anulada con éxito y las series devueltas a disponible.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al Anular Venta')
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
            'index' => Pages\ListVentas::route('/'),
            'view' => Pages\ViewVenta::route('/{record}'),
        ];
    }
}
