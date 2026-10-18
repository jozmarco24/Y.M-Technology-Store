<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\TipoDocumento;
use App\Filament\Resources\ProveedorResource\Pages;
use App\Models\Proveedor;
use App\Services\ProveedorService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProveedorResource extends Resource
{
    protected static ?string $model = Proveedor::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Compras e Inventario';

    protected static ?string $modelLabel = 'Proveedor';

    protected static ?string $pluralModelLabel = 'Proveedores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('tipo_documento')
                    ->label('Tipo de Documento')
                    ->options([
                        TipoDocumento::RUC->value => TipoDocumento::RUC->etiqueta(),
                        TipoDocumento::DNI->value => TipoDocumento::DNI->etiqueta(),
                        TipoDocumento::CE->value => TipoDocumento::CE->etiqueta(),
                    ])
                    ->required()
                    ->default(TipoDocumento::RUC->value),
                Forms\Components\TextInput::make('numero_documento')
                    ->label('Número de Documento')
                    ->required()
                    ->maxLength(20),
                Forms\Components\TextInput::make('razon_social')
                    ->label('Razón Social / Nombre')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('contacto')
                    ->label('Persona de Contacto')
                    ->maxLength(255),
                Forms\Components\TextInput::make('telefono')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(50),
                Forms\Components\TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('direccion')
                    ->label('Dirección')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('activo')
                    ->label('Activo')
                    ->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tipo_documento')
                    ->label('Tipo Doc')
                    ->badge(),
                Tables\Columns\TextColumn::make('numero_documento')
                    ->label('N° Documento')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('razon_social')
                    ->label('Razón Social')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo'),
                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Estado')
                    ->boolean()
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('alternar_activo')
                    ->label(fn (Proveedor $record): string => $record->activo ? 'Desactivar' : 'Activar')
                    ->icon(fn (Proveedor $record): string => $record->activo ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Proveedor $record): string => $record->activo ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Proveedor $record, ProveedorService $service): void {
                        $record->activo ? $service->desactivar($record) : $record->update(['activo' => true]);
                        Notification::make()
                            ->title('Estado actualizado')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->using(function (Proveedor $record, ProveedorService $service): void {
                        $service->eliminar($record);
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProveedores::route('/'),
            'create' => Pages\CreateProveedor::route('/create'),
            'edit' => Pages\EditProveedor::route('/{record}/edit'),
        ];
    }
}
