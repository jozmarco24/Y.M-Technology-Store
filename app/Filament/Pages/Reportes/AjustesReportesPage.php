<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reportes;

use App\Jobs\EnviarResumenSemanalEmailJob;
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
class AjustesReportesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $navigationLabel = 'Ajustes de Reportes';

    protected static ?string $title = 'Parámetros y Configuración de Analítica';

    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.reportes.ajustes-reportes';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user && $user->hasRole('admin');
    }

    public function mount(): void
    {
        $this->form->fill([
            'dias_stock_muerto' => (int) Configuracion::obtener('dias_stock_muerto', '90'),
            'dias_cobertura_reposicion' => (int) Configuracion::obtener('dias_cobertura_reposicion', '30'),
            'umbral_margen_bajo' => (float) Configuracion::obtener('umbral_margen_bajo', '10.0'),
            'destinatarios_resumen_semanal' => Configuracion::obtener('destinatarios_resumen_semanal', 'gerencia@tienda-syscopm.pe'),
            'dia_resumen_semanal' => Configuracion::obtener('dia_resumen_semanal', 'Monday'),
            'activar_comisiones' => (bool) (int) Configuracion::obtener('activar_comisiones', '0'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Parámetros de Inventario y Márgenes')
                    ->description('Criterios matemáticos aplicados para la detección de anomalías y sugerencias.')
                    ->schema([
                        Forms\Components\TextInput::make('dias_stock_muerto')
                            ->label('Días para considerar Stock Muerto')
                            ->helperText('Productos con stock disponible sin ventas durante este número de días.')
                            ->numeric()
                            ->required(),

                        Forms\Components\TextInput::make('dias_cobertura_reposicion')
                            ->label('Días de Cobertura Deseada (Reposición)')
                            ->helperText('Días de demanda histórica que debe cubrir el stock sugerido a comprar.')
                            ->numeric()
                            ->required(),

                        Forms\Components\TextInput::make('umbral_margen_bajo')
                            ->label('Umbral de Margen Bajo (%)')
                            ->helperText('Porcentaje mínimo de margen por debajo del cual se genera alerta.')
                            ->numeric()
                            ->suffix('%')
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Envío Automático de Resumen Ejecutivo Semanal')
                    ->description('Configuración de correo y programación del envío automático de reportes.')
                    ->schema([
                        Forms\Components\TextInput::make('destinatarios_resumen_semanal')
                            ->label('Correos Destinatarios (separados por coma)')
                            ->placeholder('gerente@empresa.com, contador@empresa.com')
                            ->required(),

                        Forms\Components\Select::make('dia_resumen_semanal')
                            ->label('Día de Envío')
                            ->options([
                                'Monday' => 'Lunes a las 07:00 AM',
                                'Friday' => 'Viernes a las 06:00 PM',
                                'Saturday' => 'Sábado a las 01:00 PM',
                            ])
                            ->default('Monday')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Módulo de Comisiones')
                    ->description('Habilita el cálculo de comisiones comerciales para los vendedores.')
                    ->schema([
                        Forms\Components\Toggle::make('activar_comisiones')
                            ->label('Activar Cálculo de Comisiones')
                            ->helperText('Habilita la tabla de comisiones en el reporte de vendedores.')
                            ->inline(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function guardar(): void
    {
        $state = $this->form->getState();

        Configuracion::establecer('dias_stock_muerto', (string) $state['dias_stock_muerto'], 'Días sin ventas para stock muerto');
        Configuracion::establecer('dias_cobertura_reposicion', (string) $state['dias_cobertura_reposicion'], 'Días de cobertura para reposición');
        Configuracion::establecer('umbral_margen_bajo', (string) $state['umbral_margen_bajo'], 'Porcentaje mínimo de margen');
        Configuracion::establecer('destinatarios_resumen_semanal', (string) $state['destinatarios_resumen_semanal'], 'Destinatarios resumen semanal');
        Configuracion::establecer('dia_resumen_semanal', (string) $state['dia_resumen_semanal'], 'Día de envío resumen semanal');
        Configuracion::establecer('activar_comisiones', $state['activar_comisiones'] ? '1' : '0', 'Flag de activación de comisiones');

        Notification::make()
            ->title('Ajustes de reportes guardados correctamente')
            ->success()
            ->send();
    }

    public function enviarResumenPrueba(): void
    {
        $state = $this->form->getState();
        $destinatariosConfig = (string) ($state['destinatarios_resumen_semanal'] ?? '');
        $emails = array_filter(array_map('trim', explode(',', $destinatariosConfig)));

        if (empty($emails)) {
            Notification::make()->title('No se han definido destinatarios válidos')->danger()->send();

            return;
        }

        EnviarResumenSemanalEmailJob::dispatch($emails);

        Notification::make()
            ->title('Resumen de prueba despachado a la cola de correos')
            ->body('Se envió el informe a: '.implode(', ', $emails))
            ->success()
            ->send();
    }
}
