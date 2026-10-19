<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PoliticaGarantiaResource\Pages;
use App\Models\Categoria;
use App\Models\PoliticaGarantia;
use App\Models\Producto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PoliticaGarantiaResource extends Resource
{
    protected static ?string $model = PoliticaGarantia::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Garantías & Postventa';

    protected static ?string $navigationLabel = 'Políticas de Garantía';

    protected static ?string $modelLabel = 'Política de Garantía';

    protected static ?string $pluralModelLabel = 'Políticas de Garantía';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user && ($user->hasRole('admin') || $user->can('politicas_garantia.gestionar'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la Política')
                    ->description('Jerarquía: Producto específico > Categoría > Política General por defecto')
                    ->schema([
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre de la Política')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\Toggle::make('activo')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(1),

                        Forms\Components\Select::make('producto_id')
                            ->label('Producto Específico (Opcional)')
                            ->options(Producto::query()->pluck('nombre', 'id'))
                            ->searchable()
                            ->nullable()
                            ->placeholder('Aplica a nivel general o por categoría')
                            ->helperText('Máxima prioridad sobre categoría y política general'),

                        Forms\Components\Select::make('categoria_id')
                            ->label('Categoría (Opcional)')
                            ->options(Categoria::query()->pluck('nombre', 'id'))
                            ->searchable()
                            ->nullable()
                            ->placeholder('Aplica a todas las categorías')
                            ->helperText('Prioridad media sobre la política general'),

                        Forms\Components\TextInput::make('meses_garantia')
                            ->label('Meses de Garantía')
                            ->numeric()
                            ->minValue(0)
                            ->default(12)
                            ->required(),

                        Forms\Components\TextInput::make('dias_cambio')
                            ->label('Días para Cambio / Devolución')
                            ->numeric()
                            ->minValue(0)
                            ->default(7)
                            ->required(),

                        Forms\Components\Textarea::make('cubre')
                            ->label('¿Qué cubre la garantía?')
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('no_cubre')
                            ->label('¿Qué NO cubre la garantía?')
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('alcance')
                    ->label('Alcance')
                    ->state(function (PoliticaGarantia $record): string {
                        if ($record->producto_id) {
                            return 'Producto: '.($record->producto?->nombre ?? 'N/A');
                        }
                        if ($record->categoria_id) {
                            return 'Categoría: '.($record->categoria?->nombre ?? 'N/A');
                        }

                        return 'General (Por Defecto)';
                    })
                    ->badge()
                    ->color(fn (PoliticaGarantia $record): string => $record->producto_id ? 'primary' : ($record->categoria_id ? 'info' : 'warning')),

                Tables\Columns\TextColumn::make('meses_garantia')
                    ->label('Meses')
                    ->alignCenter()
                    ->sortable()
                    ->suffix(' meses'),

                Tables\Columns\TextColumn::make('dias_cambio')
                    ->label('Plazo Cambio')
                    ->alignCenter()
                    ->sortable()
                    ->suffix(' días'),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPoliticasGarantia::route('/'),
            'create' => Pages\CreatePoliticaGarantia::route('/create'),
            'edit' => Pages\EditPoliticaGarantia::route('/{record}/edit'),
        ];
    }
}
