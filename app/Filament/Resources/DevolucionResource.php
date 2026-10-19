<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EstadoDevolucion;
use App\Enums\MotivoDevolucion;
use App\Enums\TipoDevolucion;
use App\Filament\Resources\DevolucionResource\Pages;
use App\Models\Devolucion;
use App\Services\DevolucionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DevolucionResource extends Resource
{
    protected static ?string $model = Devolucion::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationGroup = 'Garantías & Postventa';

    protected static ?string $navigationLabel = 'Devoluciones y Cambios';

    protected static ?string $modelLabel = 'Devolución / Cambio';

    protected static ?string $pluralModelLabel = 'Devoluciones y Cambios';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false; // Las devoluciones se inician en el asistente del POS o vía API
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user && ($user->hasRole('admin') || $user->can('devoluciones.solicitar') || $user->can('devoluciones.aprobar'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Devolución')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código')
                            ->disabled(),

                        Forms\Components\TextInput::make('venta.codigo')
                            ->label('Venta Origen')
                            ->disabled(),

                        Forms\Components\TextInput::make('cliente.nombre_razon_social')
                            ->label('Cliente')
                            ->disabled(),

                        Forms\Components\TextInput::make('tipo')
                            ->label('Tipo de Operación')
                            ->formatStateUsing(fn ($state) => $state instanceof TipoDevolucion ? $state->etiqueta() : $state)
                            ->disabled(),

                        Forms\Components\TextInput::make('motivo')
                            ->label('Motivo')
                            ->formatStateUsing(fn ($state) => $state instanceof MotivoDevolucion ? $state->etiqueta() : $state)
                            ->disabled(),

                        Forms\Components\TextInput::make('estado')
                            ->label('Estado Actual')
                            ->formatStateUsing(fn ($state) => $state instanceof EstadoDevolucion ? $state->etiqueta() : $state)
                            ->disabled(),

                        Forms\Components\TextInput::make('total_devuelto')
                            ->label('Total Devolución')
                            ->prefix('S/')
                            ->disabled(),

                        Forms\Components\TextInput::make('solicitante.name')
                            ->label('Solicitado por')
                            ->disabled(),

                        Forms\Components\TextInput::make('aprobador.name')
                            ->label('Aprobado por')
                            ->placeholder('Pendiente de aprobación')
                            ->disabled(),

                        Forms\Components\Textarea::make('detalle_motivo')
                            ->label('Detalle del Motivo / Observaciones')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(),
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
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('venta.codigo')
                    ->label('Venta')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cliente.nombre_razon_social')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (Devolucion $record): string => match ($record->tipo) {
                        TipoDevolucion::DEVOLUCION_DINERO => 'danger',
                        TipoDevolucion::CAMBIO_PRODUCTO => 'info',
                        TipoDevolucion::NOTA_CREDITO => 'warning',
                    })
                    ->formatStateUsing(fn (TipoDevolucion $state): string => $state->etiqueta()),

                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo')
                    ->formatStateUsing(fn (MotivoDevolucion $state): string => $state->etiqueta()),

                Tables\Columns\TextColumn::make('total_devuelto')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (Devolucion $record): string => match ($record->estado) {
                        EstadoDevolucion::SOLICITADA => 'warning',
                        EstadoDevolucion::APROBADA => 'info',
                        EstadoDevolucion::COMPLETADA => 'success',
                        EstadoDevolucion::RECHAZADA => 'danger',
                    })
                    ->formatStateUsing(fn (EstadoDevolucion $state): string => $state->etiqueta()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(collect(EstadoDevolucion::cases())->mapWithKeys(fn ($e) => [$e->value => $e->etiqueta()])),

                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo de Operación')
                    ->options(collect(TipoDevolucion::cases())->mapWithKeys(fn ($e) => [$e->value => $e->etiqueta()])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // Acción Aprobar (solo admin)
                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Devolucion $record): bool => $record->estado === EstadoDevolucion::SOLICITADA && Auth::user()?->can('devoluciones.aprobar'))
                    ->action(function (Devolucion $record) {
                        try {
                            app(DevolucionService::class)->aprobar($record, Auth::user());
                            Notification::make()
                                ->title('Devolución Aprobada')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al aprobar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Acción Rechazar (con motivo obligatorio, solo admin)
                Tables\Actions\Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn (Devolucion $record): bool => $record->estado === EstadoDevolucion::SOLICITADA && Auth::user()?->can('devoluciones.aprobar'))
                    ->form([
                        Forms\Components\Textarea::make('motivo_rechazo')
                            ->label('Motivo del Rechazo')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Devolucion $record, array $data) {
                        try {
                            app(DevolucionService::class)->rechazar($record, $data['motivo_rechazo'], Auth::user());
                            Notification::make()
                                ->title('Devolución Rechazada')
                                ->warning()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al rechazar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Acción Completar (admin o usuario con permiso completar)
                Tables\Actions\Action::make('completar')
                    ->label('Completar')
                    ->icon('heroicon-m-arrow-path-rounded-square')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Devolucion $record): bool => in_array($record->estado, [EstadoDevolucion::SOLICITADA, EstadoDevolucion::APROBADA], true) && Auth::user()?->can('devoluciones.completar'))
                    ->action(function (Devolucion $record) {
                        try {
                            app(DevolucionService::class)->completar($record, Auth::user());
                            Notification::make()
                                ->title('Devolución Completada con éxito')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo completar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Imprimir Constancia de Devolución
                Tables\Actions\Action::make('imprimir_constancia')
                    ->label('Constancia A4')
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->url(fn (Devolucion $record): string => route('devoluciones.imprimir_a4', $record), shouldOpenInNewTab: true),

                Tables\Actions\Action::make('ticket_constancia')
                    ->label('Ticket')
                    ->icon('heroicon-m-receipt-percent')
                    ->color('gray')
                    ->url(fn (Devolucion $record): string => route('devoluciones.imprimir_ticket', $record), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevoluciones::route('/'),
            'view' => Pages\ViewDevolucion::route('/{record}'),
        ];
    }
}
