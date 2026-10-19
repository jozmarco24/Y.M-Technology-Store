<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\ReporteVentaService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property Form $form
 */
class ReportesVentas extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $navigationLabel = 'Reporte de Ventas (Legado)';

    protected static ?string $title = 'Reporte y Análisis de Ventas';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.reportes-ventas';

    public ?string $fechaDesde = null;

    public ?string $fechaHasta = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && ($user->hasRole('admin') || $user->hasPermissionTo('reportes.ver'));
    }

    public function mount(): void
    {
        $this->fechaDesde = now()->startOfMonth()->toDateString();
        $this->fechaHasta = now()->toDateString();

        $this->form->fill([
            'fecha_desde' => $this->fechaDesde,
            'fecha_hasta' => $this->fechaHasta,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filtro de Fechas')
                    ->schema([
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Fecha Desde')
                            ->default(now()->startOfMonth())
                            ->required(),

                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Fecha Hasta')
                            ->default(now())
                            ->required(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function aplicarFiltro(): void
    {
        $state = $this->form->getState();
        $this->fechaDesde = (string) $state['fecha_desde'];
        $this->fechaHasta = (string) $state['fecha_hasta'];
    }

    /**
     * Exportar reporte consolidado a CSV.
     */
    public function exportarCsv(): StreamedResponse
    {
        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        $service = app(ReporteVentaService::class);
        $porVendedor = $service->ventasPorVendedor($desde, $hasta);
        $porMetodo = $service->ventasPorMetodoPago($desde, $hasta);
        $topProductos = $service->productosMasVendidos(50, $desde, $hasta);

        $esAdmin = Auth::user()?->hasRole('admin') ?? false;
        $margen = $esAdmin ? $service->calcularMargen(Auth::user(), $desde, $hasta) : null;

        $filename = 'reporte_ventas_'.$this->fechaDesde.'_al_'.$this->fechaHasta.'.csv';

        return response()->streamDownload(function () use ($porVendedor, $porMetodo, $topProductos, $margen, $esAdmin) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            // SECCIÓN 1: RESUMEN GENERAL Y MARGEN
            fputcsv($handle, ['--- RESUMEN GENERAL ---']);
            if ($esAdmin && $margen) {
                fputcsv($handle, ['Total Ventas (S/)', 'Costo Total (S/)', 'Margen Bruto (S/)', 'Margen (%)', 'Cant. Ventas']);
                fputcsv($handle, [
                    $margen['total_ventas'],
                    $margen['total_costo'],
                    $margen['margen_bruto'],
                    $margen['porcentaje_margen'].'%',
                    $margen['cantidad_ventas'],
                ]);
            }
            fputcsv($handle, []);

            // SECCIÓN 2: VENTAS POR VENDEDOR
            fputcsv($handle, ['--- VENTAS POR VENDEDOR ---']);
            fputcsv($handle, ['Vendedor', 'Cantidad Ventas', 'Total Vendido (S/)']);
            foreach ($porVendedor as $v) {
                fputcsv($handle, [$v['vendedor'], $v['cantidad_ventas'], $v['total_vendido']]);
            }
            fputcsv($handle, []);

            // SECCIÓN 3: VENTAS POR MÉTODO DE PAGO
            fputcsv($handle, ['--- VENTAS POR MÉTODO DE PAGO ---']);
            fputcsv($handle, ['Método de Pago', 'Transacciones', 'Total Recaudado (S/)']);
            foreach ($porMetodo as $m) {
                fputcsv($handle, [$m['etiqueta'], $m['cantidad'], $m['total']]);
            }
            fputcsv($handle, []);

            // SECCIÓN 4: PRODUCTOS MÁS VENDIDOS
            fputcsv($handle, ['--- PRODUCTOS MÁS VENDIDOS ---']);
            fputcsv($handle, ['Producto', 'Unidades Vendidas', 'Total Recaudado (S/)']);
            foreach ($topProductos as $p) {
                fputcsv($handle, [$p['producto'], $p['cantidad_vendida'], $p['total_recaudado']]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function getViewData(): array
    {
        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        $service = app(ReporteVentaService::class);

        $ventasPorVendedor = $service->ventasPorVendedor($desde, $hasta);
        $ventasPorMetodo = $service->ventasPorMetodoPago($desde, $hasta);
        $topProductos = $service->productosMasVendidos(10, $desde, $hasta);

        $esAdmin = Auth::user()?->hasRole('admin') ?? false;
        $margen = null;

        if ($esAdmin) {
            $margen = $service->calcularMargen(Auth::user(), $desde, $hasta);
        }

        return [
            'ventasPorVendedor' => $ventasPorVendedor,
            'ventasPorMetodo' => $ventasPorMetodo,
            'topProductos' => $topProductos,
            'margen' => $margen,
            'esAdmin' => $esAdmin,
        ];
    }
}
