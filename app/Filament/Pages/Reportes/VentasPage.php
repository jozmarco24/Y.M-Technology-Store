<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reportes;

use App\Models\User;
use App\Services\Reportes\ExportacionService;
use App\Services\Reportes\VentasReporteService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VentasPage extends BaseReportePage
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Ventas';

    protected static ?string $title = 'Análisis Completo de Ventas y Canales';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.reportes.ventas';

    public static function permisoRequerido(): string
    {
        return 'reportes.ver';
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetricasProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var VentasReporteService $service */
        $service = app(VentasReporteService::class);

        return $service->metricasGenerales($usuario, $desde, $hasta, $this->canal);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getComparativoMensualProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        /** @var VentasReporteService $service */
        $service = app(VentasReporteService::class);

        return $service->comparativoMensual($usuario, (int) now()->year);
    }

    /**
     * @return array{por_dia_semana: array<int, array<string, mixed>>, por_hora: array<int, array<string, mixed>>}
     */
    public function getHorasPicoProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var VentasReporteService $service */
        $service = app(VentasReporteService::class);

        return $service->horasPico($usuario, $desde, $hasta, $this->canal);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCanalPosVsWebProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var VentasReporteService $service */
        $service = app(VentasReporteService::class);

        return $service->canalPosVsWeb($usuario, $desde, $hasta);
    }

    /**
     * @return array{top_clientes: array<int, array<string, mixed>>, clientes_nuevos: int, clientes_recurrentes: int}
     */
    public function getClientesAnalisisProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var VentasReporteService $service */
        $service = app(VentasReporteService::class);

        return $service->clientesAnalisis($usuario, $desde, $hasta, 10);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getVentasPorMetodoPagoProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var VentasReporteService $service */
        $service = app(VentasReporteService::class);

        return $service->ventasPorMetodoPago($usuario, $desde, $hasta, $this->canal);
    }

    public function exportarCsv(): ?StreamedResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('CSV', 'Ventas por Metodo de Pago');
        $metodos = $this->getVentasPorMetodoPagoProperty();

        $encabezados = ['Método de Pago', 'Transacciones', 'Total Recaudado (S/)', 'Participación %'];
        $filas = array_map(function ($m) {
            return [
                'metodo' => $m['metodo'],
                'transacciones' => $m['cantidad_transacciones'],
                'total' => $m['total_monto'],
                'participacion' => $m['participacion_porcentaje'].'%',
            ];
        }, $metodos);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarCsv('ventas_metodos_pago_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }

    public function exportarExcel(): ?BinaryFileResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('Excel', 'Ventas por Metodo de Pago');
        $metodos = $this->getVentasPorMetodoPagoProperty();

        $encabezados = ['Método de Pago', 'Transacciones', 'Total Recaudado (S/)', 'Participación %'];
        $filas = array_map(function ($m) {
            return [
                'metodo' => $m['metodo'],
                'transacciones' => $m['cantidad_transacciones'],
                'total' => $m['total_monto'],
                'participacion' => $m['participacion_porcentaje'].'%',
            ];
        }, $metodos);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarExcel('ventas_metodos_pago_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }
}
