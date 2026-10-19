<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ReclamacionResource\Pages;
use App\Models\Reclamacion;
use App\Models\User;
use App\Services\ReclamacionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReclamacionResource extends Resource
{
    protected static ?string $model = Reclamacion::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Atención al Cliente';

    protected static ?string $modelLabel = 'Reclamación';

    protected static ?string $pluralModelLabel = 'Libro de Reclamaciones';

    public static function canCreate(): bool
    {
        return false; // Solo se crea desde el formulario web público
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la Hoja de Reclamación')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código')
                            ->disabled(),
                        Forms\Components\TextInput::make('tipo')
                            ->label('Tipo (Reclamo / Queja)')
                            ->disabled(),
                        Forms\Components\TextInput::make('consumidor_nombre')
                            ->label('Consumidor')
                            ->disabled(),
                        Forms\Components\TextInput::make('consumidor_numero_documento')
                            ->label('Documento')
                            ->disabled(),
                        Forms\Components\TextInput::make('consumidor_email')
                            ->label('Email')
                            ->disabled(),
                        Forms\Components\TextInput::make('consumidor_telefono')
                            ->label('Teléfono')
                            ->disabled(),
                        Forms\Components\Textarea::make('consumidor_domicilio')
                            ->label('Domicilio')
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('bien_contratado')
                            ->label('Bien Contratado')
                            ->disabled(),
                        Forms\Components\TextInput::make('monto_reclamado')
                            ->label('Monto Reclamado')
                            ->prefix('S/')
                            ->disabled(),
                        Forms\Components\Textarea::make('detalle_reclamacion')
                            ->label('Detalle de Reclamación')
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('pedido_consumidor')
                            ->label('Pedido del Consumidor')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Respuesta de la Empresa')
                    ->schema([
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'pendiente' => 'Pendiente',
                                'en_proceso' => 'En Proceso',
                                'atendido' => 'Atendido',
                                'rechazado' => 'Rechazado',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('respuesta_proveedor')
                            ->label('Respuesta al Consumidor (Formal)')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
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
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state) => $state === 'reclamo' ? 'warning' : 'danger'),
                Tables\Columns\TextColumn::make('consumidor_nombre')
                    ->label('Consumidor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha Registro')
                    ->dateTime('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('dias_restantes')
                    ->label('Plazo (15 días hábiles)')
                    ->state(fn (Reclamacion $record): string => $record->estado === 'atendido' ? 'Atendido' : "{$record->diasRestantesParaVencer()} días hábiles")
                    ->badge()
                    ->color(function (Reclamacion $record): string {
                        if ($record->estado === 'atendido') {
                            return 'success';
                        }
                        $dias = $record->diasRestantesParaVencer();
                        if ($dias <= 3) {
                            return 'danger';
                        }
                        if ($dias <= 7) {
                            return 'warning';
                        }

                        return 'info';
                    }),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'atendido' => 'success',
                        'en_proceso' => 'info',
                        'rechazado' => 'gray',
                        default => 'danger',
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('responder_rapido')
                    ->label('Responder')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->color('primary')
                    ->form([
                        Forms\Components\Textarea::make('respuesta')
                            ->label('Respuesta formal al consumidor')
                            ->required(),
                    ])
                    ->action(function (Reclamacion $record, array $data, ReclamacionService $service): void {
                        /** @var User $user */
                        $user = auth()->user();
                        $service->responder($record, $data['respuesta'], $user->id);
                        Notification::make()->title('Respuesta registrada exitosamente')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReclamacions::route('/'),
            'edit' => Pages\EditReclamacion::route('/{record}/edit'),
        ];
    }
}
