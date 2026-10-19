<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EstadoOrdenServicio;
use App\Enums\TipoOrdenServicio;
use App\Filament\Resources\OrdenServicioResource\Pages;
use App\Models\OrdenServicio;
use App\Services\OrdenServicioService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OrdenServicioResource extends Resource
{
    protected static ?string $model = OrdenServicio::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Servicio Técnico';

    protected static ?string $navigationLabel = 'Órdenes de Servicio';

    protected static ?string $modelLabel = 'Orden de Servicio';

    protected static ?string $pluralModelLabel = 'Órdenes de Servicio';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // Se reciben en el módulo POS o en recepción
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user && ($user->hasRole('admin') || $user->can('servicio.ver'));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        // Rol Técnico: solo ve sus órdenes asignadas o en diagnóstico
        if ($user && $user->hasRole('tecnico') && ! $user->hasRole('admin')) {
            $query->where('tecnico_id', $user->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Identificación de la Orden')
                        ->schema([
                            Forms\Components\TextInput::make('codigo')
                                ->label('Código')
                                ->disabled(),

                            Forms\Components\TextInput::make('tipo')
                                ->label('Tipo de Atención')
                                ->formatStateUsing(fn ($state) => $state instanceof TipoOrdenServicio ? $state->etiqueta() : $state)
                                ->disabled(),

                            Forms\Components\TextInput::make('estado')
                                ->label('Estado')
                                ->formatStateUsing(fn ($state) => $state instanceof EstadoOrdenServicio ? $state->etiqueta() : $state)
                                ->disabled(),

                            Forms\Components\TextInput::make('cliente.nombre_razon_social')
                                ->label('Cliente')
                                ->disabled(),

                            Forms\Components\TextInput::make('cliente.telefono')
                                ->label('Teléfono Cliente')
                                ->disabled(),

                            Forms\Components\TextInput::make('cliente.email')
                                ->label('Email Cliente')
                                ->disabled(),

                            Forms\Components\TextInput::make('equipo_descripcion')
                                ->label('Equipo')
                                ->state(fn (OrdenServicio $record): string => $record->producto?->nombre ?? ($record->equipo_descripcion ?: 'N/A'))
                                ->disabled(),

                            Forms\Components\TextInput::make('serie_identificador')
                                ->label('Serie')
                                ->state(fn (OrdenServicio $record): string => $record->serie?->numero_serie ?? ($record->serie_externa ?: 'Sin serie'))
                                ->disabled(),

                            Forms\Components\DatePicker::make('fecha_promesa')
                                ->label('Fecha Promesa')
                                ->disabled(),
                        ])->columns(3),

                    Forms\Components\Section::make('Diagnóstico y Solución')
                        ->schema([
                            Forms\Components\Textarea::make('falla_reportada')
                                ->label('Falla Reportada por el Cliente')
                                ->rows(2)
                                ->disabled(),

                            Forms\Components\Textarea::make('estado_recepcion')
                                ->label('Estado Físico en Recepción')
                                ->rows(2)
                                ->disabled(),

                            Forms\Components\Textarea::make('diagnostico')
                                ->label('Diagnóstico Técnico')
                                ->rows(3)
                                ->disabled(),

                            Forms\Components\Textarea::make('solucion')
                                ->label('Solución Aplicada')
                                ->rows(3)
                                ->disabled(),
                        ])->columns(2),
                ])->columnSpan(2),

                Forms\Components\Group::make([
                    Forms\Components\Section::make('Costos y Liquidación')
                        ->schema([
                            Forms\Components\TextInput::make('costo_estimado')
                                ->label('Costo Cotizado')
                                ->prefix('S/')
                                ->disabled(),

                            Forms\Components\TextInput::make('costo_final')
                                ->label('Costo Final')
                                ->prefix('S/')
                                ->disabled(),

                            Forms\Components\Toggle::make('aprobado_por_cliente')
                                ->label('Aprobado por Cliente')
                                ->disabled(),

                            Forms\Components\TextInput::make('venta.codigo')
                                ->label('Venta / Cobro Asociado')
                                ->placeholder('No cobrado')
                                ->disabled(),

                            Forms\Components\TextInput::make('garantia_reparacion_dias')
                                ->label('Días Garantía de Servicio')
                                ->suffix(' días')
                                ->disabled(),
                        ]),
                ])->columnSpan(1),
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ingreso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cliente.nombre_razon_social')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('equipo_info')
                    ->label('Equipo / Serie')
                    ->state(fn (OrdenServicio $record): string => ($record->producto?->nombre ?? $record->equipo_descripcion ?? 'Equipo').' - '.($record->serie?->numero_serie ?? $record->serie_externa ?? 'S/N'))
                    ->limit(35),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (TipoOrdenServicio $state): string => $state->color())
                    ->formatStateUsing(fn (TipoOrdenServicio $state): string => $state->etiqueta()),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (EstadoOrdenServicio $state): string => $state->color())
                    ->formatStateUsing(fn (EstadoOrdenServicio $state): string => $state->etiqueta()),

                Tables\Columns\TextColumn::make('tecnico.name')
                    ->label('Técnico')
                    ->placeholder('Sin asignar')
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha_promesa')
                    ->label('Promesa')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(function (OrdenServicio $record): string {
                        if ($record->fecha_promesa && $record->fecha_promesa->isPast() && ! in_array($record->estado, [EstadoOrdenServicio::REPARADO, EstadoOrdenServicio::ENTREGADO, EstadoOrdenServicio::CANCELADO, EstadoOrdenServicio::SIN_SOLUCION], true)) {
                            return 'danger';
                        }

                        return 'gray';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(collect(EstadoOrdenServicio::cases())->mapWithKeys(fn ($e) => [$e->value => $e->etiqueta()])),

                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(collect(TipoOrdenServicio::cases())->mapWithKeys(fn ($e) => [$e->value => $e->etiqueta()])),

                Tables\Filters\SelectFilter::make('tecnico_id')
                    ->label('Técnico Responsable')
                    ->relationship('tecnico', 'name'),

                Tables\Filters\Filter::make('atrasadas')
                    ->label('⚠️ Órdenes Atrasadas')
                    ->query(fn (Builder $query) => $query
                        ->whereNotNull('fecha_promesa')
                        ->whereDate('fecha_promesa', '<', Carbon::today())
                        ->whereNotIn('estado', [
                            EstadoOrdenServicio::REPARADO->value,
                            EstadoOrdenServicio::ENTREGADO->value,
                            EstadoOrdenServicio::CANCELADO->value,
                            EstadoOrdenServicio::SIN_SOLUCION->value,
                        ])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // Acción Diagnosticar
                Tables\Actions\Action::make('diagnosticar')
                    ->label('Diagnosticar')
                    ->icon('heroicon-m-magnifying-glass')
                    ->color('info')
                    ->visible(fn (OrdenServicio $record): bool => in_array($record->estado, [EstadoOrdenServicio::RECIBIDO, EstadoOrdenServicio::DIAGNOSTICO], true) && (Auth::user()?->can('servicio.diagnosticar') || Auth::user()?->hasRole('admin')))
                    ->form([
                        Forms\Components\Textarea::make('diagnostico')
                            ->label('Diagnóstico Técnico')
                            ->required()
                            ->rows(3)
                            ->default(fn (OrdenServicio $record) => $record->diagnostico),
                    ])
                    ->action(function (OrdenServicio $record, array $data) {
                        app(OrdenServicioService::class)->registrarDiagnostico($record, $data['diagnostico'], Auth::user());
                        Notification::make()->title('Diagnóstico registrado con éxito')->success()->send();
                    }),

                // Acción Cotizar (solo órdenes con costo)
                Tables\Actions\Action::make('cotizar')
                    ->label('Cotizar')
                    ->icon('heroicon-m-currency-dollar')
                    ->color('warning')
                    ->visible(fn (OrdenServicio $record): bool => $record->esPago() && in_array($record->estado, [EstadoOrdenServicio::RECIBIDO, EstadoOrdenServicio::DIAGNOSTICO], true))
                    ->form([
                        Forms\Components\TextInput::make('costo_estimado')
                            ->label('Costo Cotizado (Mano de obra + repuestos)')
                            ->numeric()
                            ->prefix('S/')
                            ->required(),
                    ])
                    ->action(function (OrdenServicio $record, array $data) {
                        app(OrdenServicioService::class)->cotizar($record, (float) $data['costo_estimado'], Auth::user());
                        Notification::make()->title('Cotización enviada al cliente')->warning()->send();
                    }),

                // Acción Marcar Reparado
                Tables\Actions\Action::make('reparado')
                    ->label('Marcar Reparado')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn (OrdenServicio $record): bool => in_array($record->estado, [EstadoOrdenServicio::EN_REPARACION, EstadoOrdenServicio::DIAGNOSTICO, EstadoOrdenServicio::ESPERANDO_REPUESTO], true) && (Auth::user()?->can('servicio.diagnosticar') || Auth::user()?->hasRole('admin')))
                    ->form([
                        Forms\Components\Textarea::make('solucion')
                            ->label('Solución Realizada')
                            ->required()
                            ->rows(3),
                        Forms\Components\TextInput::make('costo_final')
                            ->label('Costo Final a Cobrar')
                            ->prefix('S/')
                            ->numeric()
                            ->visible(fn (OrdenServicio $record): bool => $record->esPago()),
                    ])
                    ->action(function (OrdenServicio $record, array $data) {
                        app(OrdenServicioService::class)->marcarReparado(
                            $record,
                            $data['solucion'],
                            Auth::user(),
                            isset($data['costo_final']) ? (float) $data['costo_final'] : null
                        );
                        Notification::make()->title('Orden marcada como Reparada. Cliente notificado.')->success()->send();
                    }),

                // Enlaces de impresión
                Tables\Actions\Action::make('imprimir_ingreso')
                    ->label('Ingreso A4')
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->url(fn (OrdenServicio $record): string => route('servicio.imprimir_ingreso', $record), shouldOpenInNewTab: true),

                Tables\Actions\Action::make('imprimir_acta')
                    ->label('Acta Entrega')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('gray')
                    ->visible(fn (OrdenServicio $record): bool => in_array($record->estado, [EstadoOrdenServicio::REPARADO, EstadoOrdenServicio::ENTREGADO], true))
                    ->url(fn (OrdenServicio $record): string => route('servicio.imprimir_acta', $record), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdenesServicio::route('/'),
            'kanban' => Pages\KanbanOrdenesServicio::route('/kanban'),
            'view' => Pages\ViewOrdenServicio::route('/{record}'),
        ];
    }
}
