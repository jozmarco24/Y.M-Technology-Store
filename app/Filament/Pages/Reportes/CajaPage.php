<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reportes;

use App\Models\User;
use App\Services\Reportes\CajaReporteService;
use App\Services\Reportes\ExportacionService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CajaPage extends BaseReportePage
{
    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationLabel = 'Caja';

    protected static ?string $title = 'Reporte y Auditoría de Caja y Cierres';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.reportes.caja';

    public static function permisoRequerido(): string
    {
        return 'caja.abrir';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDiferenciasCierreProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var CajaReporteService $service */
        $service = app(CajaReporteService::class);

        return $service->diferenciasCierre($usuario, $desde, $hasta);
    }

    /**
     * @return array{ingresos: array<int, array<string, mixed>>, egresos: array<int, array<string, mixed>>, balance_neto: float}
     */
    public function getMovimientosPorConceptoProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var CajaReporteService $service */
        $service = app(CajaReporteService::class);

        return $service->movimientosPorConcepto($usuario, $desde, $hasta);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAnulacionesPorUsuarioProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var CajaReporteService $service */
        $service = app(CajaReporteService::class);

        return $service->anulacionesPorUsuario($usuario, $desde, $hasta);
    }

    public function exportarCsv(): ?StreamedResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('CSV', 'Diferencias de Cierre de Caja');
        $cierres = $this->getDiferenciasCierreProperty();

        $encabezados = ['Vendedor', 'Apertura', 'Cierre', 'Monto Esperado (S/)', 'Monto Real (S/)', 'Diferencia (S/)', 'Estado'];
        $filas = array_map(function ($item) {
            return [
                'vendedor' => $item['vendedor'],
                'abierta_en' => $item['abierta_en'],
                'cerrada_en' => $item['cerrada_en'],
                'esperado' => $item['monto_esperado'],
                'real' => $item['monto_real'],
                'diferencia' => $item['diferencia'],
                'tipo' => $item['tipo_diferencia'],
            ];
        }, $cierres);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarCsv('diferencias_caja_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }

    public function exportarExcel(): ?BinaryFileResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('Excel', 'Diferencias de Cierre de Caja');
        $cierres = $this->getDiferenciasCierreProperty();

        $encabezados = ['Vendedor', 'Apertura', 'Cierre', 'Monto Esperado (S/)', 'Monto Real (S/)', 'Diferencia (S/)', 'Estado'];
        $filas = array_map(function ($item) {
            return [
                'vendedor' => $item['vendedor'],
                'abierta_en' => $item['abierta_en'],
                'cerrada_en' => $item['cerrada_en'],
                'esperado' => $item['monto_esperado'],
                'real' => $item['monto_real'],
                'diferencia' => $item['diferencia'],
                'tipo' => $item['tipo_diferencia'],
            ];
        }, $cierres);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarExcel('diferencias_caja_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }
}
