<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductoResource\Pages;
use App\Filament\Resources\ProductoResource\RelationManagers\SeriesRelationManager;
use App\Models\Producto;
use App\Services\InventarioService;
use App\Services\ProductoService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Datos generales')
                            ->schema([
                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('nombre')
                                    ->label('Nombre del Producto')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('categoria_id')
                                    ->label('Categoría')
                                    ->relationship('categoria', 'nombre')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\Select::make('marca_id')
                                    ->label('Marca')
                                    ->relationship('marca', 'nombre')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\TextInput::make('modelo')
                                    ->label('Modelo')
                                    ->maxLength(100),
                                Forms\Components\Textarea::make('descripcion')
                                    ->label('Descripción')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('activo')
                                    ->label('Activo')
                                    ->default(true),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Precios')
                            ->schema([
                                Forms\Components\TextInput::make('precio_compra_referencial')
                                    ->label('Precio de Compra Referencial (PEN)')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->default(0)
                                    ->visible(fn (): bool => auth()->user()?->can('costos.ver') ?? false),
                                Forms\Components\TextInput::make('precio_venta')
                                    ->label('Precio de Venta (PEN)')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->required(),
                                Forms\Components\Toggle::make('incluye_igv')
                                    ->label('Incluye IGV')
                                    ->default(true),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Inventario')
                            ->schema([
                                Forms\Components\Toggle::make('controla_series')
                                    ->label('Controla Series Únicas')
                                    ->helperText('Indica si cada unidad ingresada al inventario se rastrea con un número de serie individual.')
                                    ->default(true),
                                Forms\Components\TextInput::make('stock_minimo')
                                    ->label('Stock Mínimo para Alerta')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Especificaciones')
                            ->schema([
                                Forms\Components\KeyValue::make('especificaciones')
                                    ->label('Especificaciones Técnicas')
                                    ->keyLabel('Característica (Ej. RAM, Procesador, Puerto)')
                                    ->valueLabel('Valor (Ej. 16GB DDR5, Intel Core i7, HDMI 2.1)')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Imagen y Galería')
                            ->schema([
                                Forms\Components\FileUpload::make('imagen')
                                    ->label('Imagen Principal')
                                    ->image()
                                    ->directory('productos')
                                    ->columnSpanFull(),
                                Forms\Components\Repeater::make('imagenes')
                                    ->relationship('imagenes')
                                    ->schema([
                                        Forms\Components\FileUpload::make('ruta')
                                            ->label('Imagen')
                                            ->image()
                                            ->directory('productos/galeria')
                                            ->required(),
                                        Forms\Components\TextInput::make('orden')
                                            ->numeric()
                                            ->default(0),
                                        Forms\Components\Toggle::make('es_principal')
                                            ->label('¿Es Principal?')
                                            ->default(false),
                                    ])
                                    ->columns(3)
                                    ->orderColumn('orden')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Tienda Online')
                            ->schema([
                                Forms\Components\Toggle::make('publicado_web')
                                    ->label('Publicado en Web')
                                    ->helperText('Permite que el producto aparezca en el catálogo público online.')
                                    ->default(false),
                                Forms\Components\Toggle::make('destacado')
                                    ->label('Producto Destacado')
                                    ->helperText('Aparece en la página principal y banners de destacados.')
                                    ->default(false),
                                Forms\Components\TextInput::make('peso_kg')
                                    ->label('Peso aproximado (Kg)')
                                    ->numeric()
                                    ->suffix('Kg'),
                                Forms\Components\TextInput::make('meta_titulo')
                                    ->label('Meta Título (SEO)')
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('meta_descripcion')
                                    ->label('Meta Descripción (SEO)')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('imagen')
                    ->label('Foto')
                    ->circular(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->limit(35),
                Tables\Columns\TextColumn::make('categoria.nombre')
                    ->label('Categoría')
                    ->sortable(),
                Tables\Columns\TextColumn::make('marca.nombre')
                    ->label('Marca')
                    ->sortable(),
                Tables\Columns\TextColumn::make('precio_venta')
                    ->label('Precio Venta')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_disponible')
                    ->label('Stock Disp.')
                    ->state(fn (Producto $record): int => app(InventarioService::class)->stockDisponible($record))
                    ->badge()
                    ->color(fn (int $state, Producto $record): string => $state <= $record->stock_minimo ? 'danger' : 'success')
                    ->sortable(false),
                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre'),
                Tables\Filters\SelectFilter::make('marca_id')
                    ->label('Marca')
                    ->relationship('marca', 'nombre'),
                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Estado')
                    ->boolean()
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
                Tables\Filters\Filter::make('bajo_stock')
                    ->label('Bajo Stock')
                    ->query(function (Builder $query): Builder {
                        // Filtramos productos cuyo stock actual sea <= stock_minimo
                        return $query->where(function ($q) {
                            $q->where('controla_series', true)
                                ->whereRaw('(SELECT COUNT(*) FROM series WHERE series.producto_id = productos.id AND series.estado = \'disponible\') <= productos.stock_minimo')
                                ->orWhere(function ($q2) {
                                    $q2->where('controla_series', false)
                                        ->whereRaw('(SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_inventario WHERE movimientos_inventario.producto_id = productos.id) <= productos.stock_minimo');
                                });
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('alternar_activo')
                    ->label(fn (Producto $record): string => $record->activo ? 'Desactivar' : 'Activar')
                    ->icon(fn (Producto $record): string => $record->activo ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Producto $record): string => $record->activo ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Producto $record, ProductoService $service): void {
                        $record->activo ? $service->desactivar($record) : $record->update(['activo' => true]);
                        Notification::make()
                            ->title('Estado actualizado')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            SeriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductos::route('/'),
            'create' => Pages\CreateProducto::route('/create'),
            'edit' => Pages\EditProducto::route('/{record}/edit'),
        ];
    }
}
