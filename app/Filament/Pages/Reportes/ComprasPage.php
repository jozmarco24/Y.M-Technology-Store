<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reportes;

use App\Models\Producto;
use App\Models\User;
use App\Services\Reportes\ComprasReporteService;
use App\Services\Reportes\ExportacionService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComprasPage extends BaseReportePage
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Compras';

    protected static ?string $title = 'Reporte de Compras y Evolución de Costos';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.reportes.compras';

    public ?int $productoSeleccionadoId = null;

    public static function permisoRequerido(): string
    {
        return 'compras.ver';
    }

    public function mount(): void
    {
        parent::mount();

        $primerProducto = Producto::where('activo', true)->first();
        if ($primerProducto) {
            $this->productoSeleccionadoId = $primerProducto->id;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getComprasPorProveedorProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var ComprasReporteService $service */
        $service = app(ComprasReporteService::class);

        return $service->comprasPorProveedor($usuario, $desde, $hasta);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEvolucionCostoProperty(): array
    {
        if (! $this->productoSeleccionadoId) {
            return [];
        }

        /** @var User $usuario */
        $usuario = Auth::user();

        /** @var ComprasReporteService $service */
        $service = app(ComprasReporteService::class);

        return $service->evolucionCostoProducto($usuario, $this->productoSeleccionadoId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getComparativoProveedoresProperty(): array
    {
        if (! $this->productoSeleccionadoId) {
            return [];
        }

        /** @var User $usuario */
        $usuario = Auth::user();

        /** @var ComprasReporteService $service */
        $service = app(ComprasReporteService::class);

        return $service->comparativoCostoProveedores($usuario, $this->productoSeleccionadoId);
    }

    public function exportarCsv(): ?StreamedResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('CSV', 'Compras por Proveedor');
        $compras = $this->getComprasPorProveedorProperty();

        $encabezados = ['Proveedor', 'RUC / Doc', 'Cantidad Compras'];
        if ($this->puedeVerMargen()) {
            $encabezados[] = 'Subtotal (S/)';
            $encabezados[] = 'IGV (S/)';
            $encabezados[] = 'Total (S/)';
        }

        $filas = array_map(function ($item) {
            $row = [
                'proveedor' => $item['razon_social'],
                'documento' => $item['numero_documento'],
                'cantidad' => $item['cantidad_compras'],
            ];

            if ($this->puedeVerMargen()) {
                $row['subtotal'] = $item['subtotal_compras'] ?? 0;
                $row['igv'] = $item['igv_compras'] ?? 0;
                $row['total'] = $item['total_compras'] ?? 0;
            }

            return $row;
        }, $compras);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarCsv('compras_proveedores_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }

    public function exportarExcel(): ?BinaryFileResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('Excel', 'Compras por Proveedor');
        $compras = $this->getComprasPorProveedorProperty();

        $encabezados = ['Proveedor', 'RUC / Doc', 'Cantidad Compras'];
        if ($this->puedeVerMargen()) {
            $encabezados[] = 'Subtotal (S/)';
            $encabezados[] = 'IGV (S/)';
            $encabezados[] = 'Total (S/)';
        }

        $filas = array_map(function ($item) {
            $row = [
                'proveedor' => $item['razon_social'],
                'documento' => $item['numero_documento'],
                'cantidad' => $item['cantidad_compras'],
            ];

            if ($this->puedeVerMargen()) {
                $row['subtotal'] = $item['subtotal_compras'] ?? 0;
                $row['igv'] = $item['igv_compras'] ?? 0;
                $row['total'] = $item['total_compras'] ?? 0;
            }

            return $row;
        }, $compras);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarExcel('compras_proveedores_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }
}
