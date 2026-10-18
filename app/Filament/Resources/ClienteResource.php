<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\TipoDocumentoCliente;
use App\Filament\Resources\ClienteResource\Pages;
use App\Filament\Resources\ClienteResource\RelationManagers\VentasRelationManager;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Ventas & Clientes';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Cliente')
                    ->schema([
                        Forms\Components\Select::make('tipo_documento')
                            ->label('Tipo de Documento')
                            ->options(collect(TipoDocumentoCliente::cases())->mapWithKeys(fn ($t) => [$t->value => $t->etiqueta()]))
                            ->default(TipoDocumentoCliente::DNI->value)
                            ->required()
                            ->native(false)
                            ->live(),

                        Forms\Components\TextInput::make('numero_documento')
                            ->label('Número de Documento')
                            ->required(fn (Forms\Get $get) => $get('tipo_documento') !== TipoDocumentoCliente::SIN_DOC->value)
                            ->maxLength(20)
                            ->unique(Cliente::class, 'numero_documento', ignoreRecord: true),

                        Forms\Components\TextInput::make('nombre_razon_social')
                            ->label('Nombre o Razón Social')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('direccion')
                            ->label('Dirección')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\Toggle::make('activo')
                            ->label('Activo')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tipo_documento')
                    ->label('Tipo Doc.')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->value ?? $state),

                Tables\Columns\TextColumn::make('numero_documento')
                    ->label('Número')
                    ->searchable()
                    ->sortable()
                    ->placeholder('SIN DOC')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nombre_razon_social')
                    ->label('Nombre o Razón Social')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ventas_count')
                    ->label('Compras')
                    ->counts('ventas')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nombre_razon_social')
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_documento')
                    ->label('Tipo de Documento')
                    ->options(collect(TipoDocumentoCliente::cases())->mapWithKeys(fn ($t) => [$t->value => $t->etiqueta()])),

                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Solo Activos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            VentasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientes::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'edit' => Pages\EditCliente::route('/{record}/edit'),
        ];
    }
}
