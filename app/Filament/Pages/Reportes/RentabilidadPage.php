<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reportes;

use App\Models\User;
use App\Services\Reportes\ExportacionService;
use App\Services\Reportes\RentabilidadService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentabilidadPage extends BaseReportePage
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Rentabilidad';

    protected static ?string $title = 'Análisis de Rentabilidad y Márgenes';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.reportes.rentabilidad';

    public static function permisoRequerido(): string
    {
        return 'reportes.ver_margen';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMargenPorProductoProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var RentabilidadService $service */
        $service = app(RentabilidadService::class);

        return $service->margenPorProducto($usuario, $desde, $hasta, $this->canal, 100);
    }

    /**
     * @return array{mas_rentables: array<int, array<string, mixed>>, menos_rentables: array<int, array<string, mixed>>}
     */
    public function getTopRentabilidadProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var RentabilidadService $service */
        $service = app(RentabilidadService::class);

        return $service->topRentabilidad($usuario, $desde, $hasta, $this->canal);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getVentasBajoMargenProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var RentabilidadService $service */
        $service = app(RentabilidadService::class);

        return $service->ventasBajoMargen($usuario, $desde, $hasta);
    }

    public function exportarCsv(): ?StreamedResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('CSV', 'Rentabilidad por Producto');
        $datos = $this->getMargenPorProductoProperty();

        $encabezados = ['Producto', 'SKU', 'Categoría', 'Marca', 'Unidades', 'Venta Neta', 'Costo Total', 'Margen (S/)', 'Margen (%)'];
        $filas = array_map(function ($item) {
            return [
                'producto' => $item['producto_nombre'],
                'sku' => $item['sku'],
                'categoria' => $item['categoria'],
                'marca' => $item['marca'],
                'unidades' => $item['unidades_vendidas'],
                'venta_neta' => $item['venta_neta_sin_igv'],
                'costo_total' => $item['costo_total'] ?? 0,
                'margen_monto' => $item['margen_monto'] ?? 0,
                'margen_porcentaje' => ($item['margen_porcentaje'] ?? 0).'%',
            ];
        }, $datos);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarCsv('rentabilidad_productos_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }

    public function exportarExcel(): ?BinaryFileResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('Excel', 'Rentabilidad por Producto');
        $datos = $this->getMargenPorProductoProperty();

        $encabezados = ['Producto', 'SKU', 'Categoría', 'Marca', 'Unidades', 'Venta Neta', 'Costo Total', 'Margen (S/)', 'Margen (%)'];
        $filas = array_map(function ($item) {
            return [
                'producto' => $item['producto_nombre'],
                'sku' => $item['sku'],
                'categoria' => $item['categoria'],
                'marca' => $item['marca'],
                'unidades' => $item['unidades_vendidas'],
                'venta_neta' => $item['venta_neta_sin_igv'],
                'costo_total' => $item['costo_total'] ?? 0,
                'margen_monto' => $item['margen_monto'] ?? 0,
                'margen_porcentaje' => $item['margen_porcentaje'] ?? 0,
            ];
        }, $datos);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarExcel('rentabilidad_productos_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }
}
