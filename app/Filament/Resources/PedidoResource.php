<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EstadoPagoOnline;
use App\Enums\EstadoPedido;
use App\Enums\MetodoEntrega;
use App\Enums\Pasarela;
use App\Filament\Resources\PedidoResource\Pages;
use App\Models\PagoOnline;
use App\Models\Pedido;
use App\Models\User;
use App\Services\PagoOnlineService;
use App\Services\PedidoService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PedidoResource extends Resource
{
    protected static ?string $model = Pedido::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Ventas Web';

    protected static ?string $modelLabel = 'Pedido Online';

    protected static ?string $pluralModelLabel = 'Pedidos Online';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // Los pedidos solo se crean desde el carrito web
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Detalle del Pedido')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('General')
                            ->schema([
                                Forms\Components\TextInput::make('codigo')
                                    ->label('Código')
                                    ->disabled(),
                                Forms\Components\Select::make('estado')
                                    ->label('Estado')
                                    ->options(collect(EstadoPedido::cases())->mapWithKeys(fn ($e) => [$e->value => $e->etiqueta()]))
                                    ->disabled(),
                                Forms\Components\TextInput::make('email_contacto')
                                    ->label('Email de Contacto')
                                    ->disabled(),
                                Forms\Components\TextInput::make('telefono_contacto')
                                    ->label('Teléfono de Contacto')
                                    ->disabled(),
                                Forms\Components\TextInput::make('metodo_entrega')
                                    ->label('Método de Entrega')
                                    ->formatStateUsing(fn ($state) => $state instanceof MetodoEntrega ? $state->etiqueta() : (MetodoEntrega::tryFrom((string) $state)?->etiqueta() ?? $state))
                                    ->disabled(),
                                Forms\Components\TextInput::make('reserva_expira_en')
                                    ->label('Reserva Expira En')
                                    ->disabled(),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Productos y Series')
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->relationship('items')
                                    ->schema([
                                        Forms\Components\TextInput::make('descripcion')
                                            ->label('Producto')
                                            ->disabled(),
                                        Forms\Components\TextInput::make('cantidad')
                                            ->label('Cantidad')
                                            ->disabled(),
                                        Forms\Components\TextInput::make('precio_unitario')
                                            ->label('Precio Unit.')
                                            ->prefix('S/')
                                            ->disabled(),
                                        Forms\Components\TextInput::make('subtotal')
                                            ->label('Subtotal')
                                            ->prefix('S/')
                                            ->disabled(),
                                    ])
                                    ->columns(4)
                                    ->disabled()
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Entrega y Dirección')
                            ->schema([
                                Forms\Components\KeyValue::make('direccion_snapshot')
                                    ->label('Datos de Envío')
                                    ->disabled()
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('courier')
                                    ->label('Empresa Courier')
                                    ->disabled(),
                                Forms\Components\TextInput::make('tracking_codigo')
                                    ->label('Código de Tracking')
                                    ->disabled(),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Facturación y Totales')
                            ->schema([
                                Forms\Components\TextInput::make('tipo_comprobante')
                                    ->label('Tipo de Comprobante')
                                    ->disabled(),
                                Forms\Components\TextInput::make('documento_tipo')
                                    ->label('Tipo Documento')
                                    ->disabled(),
                                Forms\Components\TextInput::make('documento_numero')
                                    ->label('Número Documento')
                                    ->disabled(),
                                Forms\Components\TextInput::make('razon_social')
                                    ->label('Razón Social')
                                    ->disabled(),
                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->prefix('S/')
                                    ->disabled(),
                                Forms\Components\TextInput::make('costo_envio')
                                    ->label('Costo Envío')
                                    ->prefix('S/')
                                    ->disabled(),
                                Forms\Components\TextInput::make('descuento')
                                    ->label('Descuento')
                                    ->prefix('S/')
                                    ->disabled(),
                                Forms\Components\TextInput::make('total')
                                    ->label('Total')
                                    ->prefix('S/')
                                    ->disabled(),
                            ])->columns(2),
                    ])->columnSpanFull(),
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
                    ->weight('bold')
                    ->copyable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('email_contacto')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('metodo_entrega')
                    ->label('Entrega')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof MetodoEntrega ? $state->etiqueta() : (MetodoEntrega::tryFrom((string) $state)?->etiqueta() ?? $state)),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (Pedido $record) => $record->estado->color())
                    ->formatStateUsing(fn (Pedido $record) => $record->estado->etiqueta()),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(collect(EstadoPedido::cases())->mapWithKeys(fn ($e) => [$e->value => $e->etiqueta()])),
                Tables\Filters\SelectFilter::make('metodo_entrega')
                    ->label('Método de Entrega')
                    ->options(collect(MetodoEntrega::cases())->mapWithKeys(fn ($e) => [$e->value => $e->etiqueta()])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // Acción 1: Marcar en preparación
                Tables\Actions\Action::make('marcar_preparando')
                    ->label('Preparar')
                    ->icon('heroicon-o-cube')
                    ->color('info')
                    ->visible(fn (Pedido $record) => $record->estado === EstadoPedido::PAGADO && (auth()->user()?->can('pedidos.gestionar') ?? false))
                    ->requiresConfirmation()
                    ->action(function (Pedido $record, PedidoService $service): void {
                        /** @var User $actor */
                        $actor = auth()->user();
                        $service->cambiarEstado($record, EstadoPedido::PREPARANDO, 'Pedido puesto en preparación de empaque.', $actor);
                        Notification::make()->title('Pedido marcado como En Preparación')->success()->send();
                    }),

                // Acción 2: Marcar enviado con Courier y Tracking
                Tables\Actions\Action::make('marcar_enviado')
                    ->label('Enviar')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(fn (Pedido $record) => in_array($record->estado, [EstadoPedido::PAGADO, EstadoPedido::PREPARANDO]) && (auth()->user()?->can('pedidos.gestionar') ?? false))
                    ->form([
                        Forms\Components\TextInput::make('courier')
                            ->label('Empresa de Courier (Olva, Shalom, etc.)')
                            ->required(),
                        Forms\Components\TextInput::make('tracking_codigo')
                            ->label('Número de Guía / Tracking')
                            ->required(),
                    ])
                    ->action(function (Pedido $record, array $data, PedidoService $service): void {
                        /** @var User $actor */
                        $actor = auth()->user();
                        $service->marcarEnviado($record, $data['courier'], $data['tracking_codigo'], $actor);
                        Notification::make()->title('Pedido marcado como Enviado')->success()->send();
                    }),

                // Acción 3: Marcar entregado
                Tables\Actions\Action::make('marcar_entregado')
                    ->label('Entregado')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Pedido $record) => in_array($record->estado, [EstadoPedido::PAGADO, EstadoPedido::PREPARANDO, EstadoPedido::ENVIADO]) && (auth()->user()?->can('pedidos.gestionar') ?? false))
                    ->requiresConfirmation()
                    ->action(function (Pedido $record, PedidoService $service): void {
                        /** @var User $actor */
                        $actor = auth()->user();
                        $service->marcarEntregado($record, $actor);
                        Notification::make()->title('Pedido marcado como Entregado')->success()->send();
                    }),

                // Acción 4: Confirmar pago manual (Yape / Transferencia)
                Tables\Actions\Action::make('confirmar_pago_manual')
                    ->label('Confirmar Pago Manual')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Pedido $record) => $record->estado === EstadoPedido::PENDIENTE_PAGO && (auth()->user()?->can('pedidos.gestionar') ?? false))
                    ->form([
                        Forms\Components\TextInput::make('referencia')
                            ->label('Número de Operación Bancaria / Yape')
                            ->required(),
                    ])
                    ->action(function (Pedido $record, array $data, PagoOnlineService $pagoService): void {
                        $pago = $record->pagosOnline()->latest()->first();
                        if (! $pago) {
                            $inicio = $pagoService->iniciar($record, Pasarela::TRANSFERENCIA);
                            $pago = PagoOnline::find($inicio['pago_id']);
                        }
                        if ($pago) {
                            $pagoService->confirmar($pago, ['referencia' => $data['referencia']]);
                            Notification::make()->title('Pago confirmado y convertido a venta')->success()->send();
                        }
                    }),

                // Acción 5: Cancelar pedido (libera series)
                Tables\Actions\Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Pedido $record) => in_array($record->estado, [EstadoPedido::PENDIENTE_PAGO, EstadoPedido::PREPARANDO]) && (auth()->user()?->can('pedidos.cancelar') ?? false))
                    ->form([
                        Forms\Components\Textarea::make('motivo')
                            ->label('Motivo de Cancelación')
                            ->required(),
                    ])
                    ->action(function (Pedido $record, array $data, PedidoService $service): void {
                        /** @var User $actor */
                        $actor = auth()->user();
                        $service->cancelar($record, $data['motivo'], $actor);
                        Notification::make()->title('Pedido cancelado y stock liberado')->danger()->send();
                    }),

                // Acción 6: Reembolsar
                Tables\Actions\Action::make('reembolsar')
                    ->label('Reembolsar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Pedido $record) => in_array($record->estado, [EstadoPedido::PAGADO, EstadoPedido::REEMBOLSADO]) && (auth()->user()?->can('pedidos.reembolsar') ?? false))
                    ->form([
                        Forms\Components\TextInput::make('monto')
                            ->label('Monto a Reembolsar (PEN)')
                            ->numeric()
                            ->default(fn (Pedido $record) => $record->total)
                            ->required(),
                        Forms\Components\Textarea::make('motivo')
                            ->label('Motivo del Reembolso')
                            ->required(),
                    ])
                    ->action(function (Pedido $record, array $data, PagoOnlineService $pagoService): void {
                        $pago = $record->pagosOnline()->where('estado', EstadoPagoOnline::PAGADO)->latest()->first();
                        if ($pago) {
                            /** @var User $actor */
                            $actor = auth()->user();
                            $pagoService->reembolsar($pago, (float) $data['monto'], $data['motivo'], $actor);
                            Notification::make()->title('Reembolso procesado exitosamente')->success()->send();
                        } else {
                            Notification::make()->title('No se encontró un pago confirmado para reembolsar')->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPedidos::route('/'),
            'edit' => Pages\EditPedido::route('/{record}/edit'),
        ];
    }
}
