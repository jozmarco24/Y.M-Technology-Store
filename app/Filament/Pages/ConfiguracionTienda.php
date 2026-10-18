<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Configuracion;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * @property Form $form
 */
class ConfiguracionTienda extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Configuración de Tienda';

    protected static ?string $title = 'Configuración de la Tienda';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.configuracion-tienda';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->hasRole('admin');
    }

    public function mount(): void
    {
        $this->form->fill([
            'tienda_nombre' => Configuracion::obtener('tienda_nombre', 'TIENDA DE TECNOLOGÍA & HARDWARE'),
            'tienda_ruc' => Configuracion::obtener('tienda_ruc', '20601234568'),
            'tienda_razon_social' => Configuracion::obtener('tienda_razon_social', 'SYS-COPM IMPORTADORA S.A.C.'),
            'tienda_direccion' => Configuracion::obtener('tienda_direccion', 'Av. Garcilaso de la Vega 1348, Int. 204, Lima'),
            'tienda_telefono' => Configuracion::obtener('tienda_telefono', '(01) 424-5566'),
            'tienda_email' => Configuracion::obtener('tienda_email', 'ventas@tienda-syscopm.pe'),
            'tienda_mensaje_pie' => Configuracion::obtener('tienda_mensaje_pie', '¡Gracias por su preferencia!'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos Fiscales y Comerciales')
                    ->description('Estos datos aparecen en los comprobantes y tickets térmicos de 80mm emitidos por el POS.')
                    ->schema([
                        Forms\Components\TextInput::make('tienda_nombre')
                            ->label('Nombre Comercial')
                            ->required(),

                        Forms\Components\TextInput::make('tienda_ruc')
                            ->label('RUC')
                            ->required()
                            ->length(11),

                        Forms\Components\TextInput::make('tienda_razon_social')
                            ->label('Razón Social Legal')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('tienda_direccion')
                            ->label('Dirección Fiscal y Comercial')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('tienda_telefono')
                            ->label('Teléfonos de Contacto'),

                        Forms\Components\TextInput::make('tienda_email')
                            ->label('Correo Electrónico de Ventas')
                            ->email(),

                        Forms\Components\Textarea::make('tienda_mensaje_pie')
                            ->label('Mensaje al Pie del Ticket')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function guardar(): void
    {
        $state = $this->form->getState();

        foreach ($state as $clave => $valor) {
            Configuracion::establecer((string) $clave, is_string($valor) ? $valor : (string) $valor);
        }

        Notification::make()
            ->title('Configuración Actualizada')
            ->body('Los datos de la tienda han sido guardados exitosamente.')
            ->success()
            ->send();
    }
}
