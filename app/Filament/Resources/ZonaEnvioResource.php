<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZonaEnvioResource\Pages;
use App\Models\ZonaEnvio;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ZonaEnvioResource extends Resource
{
    protected static ?string $model = ZonaEnvio::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Configuración Web';

    protected static ?string $modelLabel = 'Zona de Envío';

    protected static ?string $pluralModelLabel = 'Zonas de Envío';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('departamento')
                    ->maxLength(50),
                Forms\Components\TextInput::make('provincia')
                    ->maxLength(50),
                Forms\Components\TextInput::make('distrito')
                    ->maxLength(50),
                Forms\Components\TextInput::make('costo')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('dias_estimados')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\Toggle::make('activo')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('departamento')
                    ->searchable(),
                Tables\Columns\TextColumn::make('provincia')
                    ->searchable(),
                Tables\Columns\TextColumn::make('distrito')
                    ->searchable(),
                Tables\Columns\TextColumn::make('costo')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('dias_estimados')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListZonaEnvios::route('/'),
            'create' => Pages\CreateZonaEnvio::route('/create'),
            'edit' => Pages\EditZonaEnvio::route('/{record}/edit'),
        ];
    }
}
