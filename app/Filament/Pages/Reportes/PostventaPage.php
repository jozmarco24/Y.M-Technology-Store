<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reportes;

use App\Models\User;
use App\Services\Reportes\ExportacionService;
use App\Services\Reportes\PostventaReporteService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostventaPage extends BaseReportePage
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Postventa';

    protected static ?string $title = 'Reporte de Postventa, Garantías y Servicio Técnico';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.reportes.postventa';

    public static function permisoRequerido(): string
    {
        return 'servicio.ver';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTasaFallasProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        /** @var PostventaReporteService $service */
        $service = app(PostventaReporteService::class);

        return $service->tasaFallas($usuario);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTiemposReparacionProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        /** @var PostventaReporteService $service */
        $service = app(PostventaReporteService::class);

        return $service->tiemposReparacion($usuario);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCostoGarantiasProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        /** @var PostventaReporteService $service */
        $service = app(PostventaReporteService::class);

        return $service->costoGarantiasMensual($usuario, (int) now()->year);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDevolucionesAnalisisProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var PostventaReporteService $service */
        $service = app(PostventaReporteService::class);

        return $service->devolucionesAnalisis($usuario, $desde, $hasta);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGarantiasPorVencerProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        /** @var PostventaReporteService $service */
        $service = app(PostventaReporteService::class);

        return $service->garantiasPorVencer($usuario, 30);
    }

    public function exportarCsv(): ?StreamedResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('CSV', 'Tasa de Fallas por Modelo');
        $fallas = $this->getTasaFallasProperty();

        $encabezados = ['Producto', 'SKU', 'Marca', 'Categoría', 'Unidades Vendidas', 'Órdenes de Servicio', 'Tasa de Fallas (%)'];
        $filas = array_map(function ($item) {
            return [
                'producto' => $item['producto'],
                'sku' => $item['sku'],
                'marca' => $item['marca'],
                'categoria' => $item['categoria'],
                'unidades' => $item['unidades_vendidas'],
                'ordenes' => $item['ordenes_servicio'],
                'tasa' => $item['tasa_fallas_porcentaje'].'%',
            ];
        }, $fallas);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarCsv('postventa_tasa_fallas_'.now()->format('Ymd'), $encabezados, $filas);
    }

    public function exportarExcel(): ?BinaryFileResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('Excel', 'Tasa de Fallas por Modelo');
        $fallas = $this->getTasaFallasProperty();

        $encabezados = ['Producto', 'SKU', 'Marca', 'Categoría', 'Unidades Vendidas', 'Órdenes de Servicio', 'Tasa de Fallas (%)'];
        $filas = array_map(function ($item) {
            return [
                'producto' => $item['producto'],
                'sku' => $item['sku'],
                'marca' => $item['marca'],
                'categoria' => $item['categoria'],
                'unidades' => $item['unidades_vendidas'],
                'ordenes' => $item['ordenes_servicio'],
                'tasa' => $item['tasa_fallas_porcentaje'].'%',
            ];
        }, $fallas);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarExcel('postventa_tasa_fallas_'.now()->format('Ymd'), $encabezados, $filas);
    }
}
