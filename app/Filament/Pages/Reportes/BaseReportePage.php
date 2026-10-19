<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reportes;

use App\Models\User;
use Carbon\Carbon;
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
abstract class BaseReportePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Reportes';

    public ?string $rangoRapido = 'mes';

    public ?string $fechaDesde = null;

    public ?string $fechaHasta = null;

    public string $canal = 'todos';

    public bool $compararPeriodoAnterior = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filtroData = [];

    /**
     * Define qué permiso se requiere para ver esta página de reporte.
     */
    abstract public static function permisoRequerido(): string;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasPermissionTo(static::permisoRequerido());
    }

    public function mount(): void
    {
        $this->aplicarRangoRapido('mes');

        $this->form->fill([
            'rango_rapido' => $this->rangoRapido,
            'fecha_desde' => $this->fechaDesde,
            'fecha_hasta' => $this->fechaHasta,
            'canal' => $this->canal,
            'comparar' => $this->compararPeriodoAnterior,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make([
                    'default' => 1,
                    'sm' => 2,
                    'md' => 4,
                    'lg' => 6,
                ])->schema([
                    Forms\Components\Select::make('rango_rapido')
                        ->label('Periodo')
                        ->options([
                            'hoy' => 'Hoy',
                            '7_dias' => 'Últimos 7 días',
                            'mes' => 'Este mes',
                            'mes_anterior' => 'Mes anterior',
                            'anio' => 'Este año',
                            'personalizado' => 'Personalizado',
                        ])
                        ->default('mes')
                        ->reactive()
                        ->afterStateUpdated(function (string $state) {
                            $this->aplicarRangoRapido($state);
                            $this->form->fill([
                                'rango_rapido' => $state,
                                'fecha_desde' => $this->fechaDesde,
                                'fecha_hasta' => $this->fechaHasta,
                                'canal' => $this->canal,
                                'comparar' => $this->compararPeriodoAnterior,
                            ]);
                        }),

                    Forms\Components\DatePicker::make('fecha_desde')
                        ->label('Desde')
                        ->required()
                        ->reactive(),

                    Forms\Components\DatePicker::make('fecha_hasta')
                        ->label('Hasta')
                        ->required()
                        ->reactive(),

                    Forms\Components\Select::make('canal')
                        ->label('Canal')
                        ->options([
                            'todos' => 'Todos los canales',
                            'pos' => 'Tienda física (POS)',
                            'web' => 'E-Commerce (Web)',
                        ])
                        ->default('todos'),

                    Forms\Components\Toggle::make('comparar')
                        ->label('Comparar anterior')
                        ->inline(false)
                        ->default(false),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('filtrar')
                            ->label('Actualizar')
                            ->icon('heroicon-m-arrow-path')
                            ->action('aplicarFiltros'),
                    ])->extraAttributes(['class' => 'flex items-end']),
                ]),
            ])
            ->statePath('filtroData');
    }

    public function aplicarRangoRapido(string $rango): void
    {
        $this->rangoRapido = $rango;

        switch ($rango) {
            case 'hoy':
                $this->fechaDesde = Carbon::today()->toDateString();
                $this->fechaHasta = Carbon::today()->toDateString();
                break;
            case '7_dias':
                $this->fechaDesde = Carbon::today()->subDays(6)->toDateString();
                $this->fechaHasta = Carbon::today()->toDateString();
                break;
            case 'mes':
                $this->fechaDesde = Carbon::now()->startOfMonth()->toDateString();
                $this->fechaHasta = Carbon::now()->toDateString();
                break;
            case 'mes_anterior':
                $this->fechaDesde = Carbon::now()->subMonth()->startOfMonth()->toDateString();
                $this->fechaHasta = Carbon::now()->subMonth()->endOfMonth()->toDateString();
                break;
            case 'anio':
                $this->fechaDesde = Carbon::now()->startOfYear()->toDateString();
                $this->fechaHasta = Carbon::now()->toDateString();
                break;
            case 'personalizado':
                // Mantener los valores actuales
                break;
        }
    }

    public function aplicarFiltros(): void
    {
        $state = $this->form->getState();
        $this->rangoRapido = (string) ($state['rango_rapido'] ?? 'personalizado');
        $this->fechaDesde = (string) ($state['fecha_desde'] ?? $this->fechaDesde);
        $this->fechaHasta = (string) ($state['fecha_hasta'] ?? $this->fechaHasta);
        $this->canal = (string) ($state['canal'] ?? 'todos');
        $this->compararPeriodoAnterior = (bool) ($state['comparar'] ?? false);

        Notification::make()
            ->title('Reporte actualizado')
            ->success()
            ->send();
    }

    protected function registrarActividadExportacion(string $tipoExportacion, string $nombreReporte): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user) {
            activity('reportes')
                ->causedBy($user)
                ->withProperties([
                    'reporte' => $nombreReporte,
                    'formato' => $tipoExportacion,
                    'fecha_desde' => $this->fechaDesde,
                    'fecha_hasta' => $this->fechaHasta,
                    'canal' => $this->canal,
                ])
                ->log("Exportó el reporte {$nombreReporte} a formato {$tipoExportacion}");
        }
    }

    public function puedeVerMargen(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user && ($user->hasRole('admin') || $user->hasPermissionTo('reportes.ver_margen'));
    }

    public function puedeExportar(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user && ($user->hasRole('admin') || $user->hasPermissionTo('reportes.exportar'));
    }
}
