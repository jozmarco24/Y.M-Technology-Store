<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reportes;

use App\Models\User;
use App\Services\Reportes\ExportacionService;
use App\Services\Reportes\ResumenEjecutivoService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumenEjecutivoPage extends BaseReportePage
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Resumen Ejecutivo';

    protected static ?string $title = 'Resumen Ejecutivo y Métricas Clave';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.reportes.resumen-ejecutivo';

    public static function permisoRequerido(): string
    {
        return 'reportes.ver_margen'; // Solo gerencia / admin
    }

    /**
     * @return array<string, mixed>
     */
    public function getResumenProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var ResumenEjecutivoService $service */
        $service = app(ResumenEjecutivoService::class);

        return $service->generar($usuario, $desde, $hasta);
    }

    public function exportarCsv(): ?StreamedResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('CSV', 'Resumen Ejecutivo');
        $resumen = $this->getResumenProperty();

        $encabezados = ['Métrica', 'Valor Actual', 'Periodo Anterior', 'Variación %'];
        $filas = [
            [
                'metrica' => 'Ventas Netas',
                'actual' => 'S/ '.number_format($resumen['ventas']['venta_neta'], 2),
                'anterior' => 'S/ '.number_format($resumen['ventas']['venta_neta_anterior'], 2),
                'variacion' => $resumen['ventas']['variacion_ventas_porcentaje'].'%',
            ],
            [
                'metrica' => 'Total Pedidos',
                'actual' => (string) $resumen['ventas']['total_pedidos'],
                'anterior' => '-',
                'variacion' => '-',
            ],
            [
                'metrica' => 'Ticket Promedio',
                'actual' => 'S/ '.number_format($resumen['ventas']['ticket_promedio'], 2),
                'anterior' => '-',
                'variacion' => '-',
            ],
            [
                'metrica' => 'Devoluciones Totales',
                'actual' => 'S/ '.number_format($resumen['postventa']['total_devuelto'], 2),
                'anterior' => '-',
                'variacion' => $resumen['postventa']['tasa_devolucion_porcentaje'].'% de ventas',
            ],
        ];

        if (isset($resumen['rentabilidad'])) {
            $filas[] = [
                'metrica' => 'Margen Bruto',
                'actual' => 'S/ '.number_format($resumen['rentabilidad']['margen_bruto'], 2),
                'anterior' => 'S/ '.number_format($resumen['rentabilidad']['margen_anterior'], 2),
                'variacion' => $resumen['rentabilidad']['variacion_margen_porcentaje'].'%',
            ];
        }

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarCsv('resumen_ejecutivo_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }

    public function exportarExcel(): ?BinaryFileResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('Excel', 'Resumen Ejecutivo');
        $resumen = $this->getResumenProperty();

        $encabezados = ['Métrica', 'Valor Actual', 'Periodo Anterior', 'Variación %'];
        $filas = [
            [
                'metrica' => 'Ventas Netas',
                'actual' => $resumen['ventas']['venta_neta'],
                'anterior' => $resumen['ventas']['venta_neta_anterior'],
                'variacion' => $resumen['ventas']['variacion_ventas_porcentaje'].'%',
            ],
            [
                'metrica' => 'Total Pedidos',
                'actual' => $resumen['ventas']['total_pedidos'],
                'anterior' => '-',
                'variacion' => '-',
            ],
            [
                'metrica' => 'Ticket Promedio',
                'actual' => $resumen['ventas']['ticket_promedio'],
                'anterior' => '-',
                'variacion' => '-',
            ],
            [
                'metrica' => 'Devoluciones Totales',
                'actual' => $resumen['postventa']['total_devuelto'],
                'anterior' => '-',
                'variacion' => $resumen['postventa']['tasa_devolucion_porcentaje'].'%',
            ],
        ];

        if (isset($resumen['rentabilidad'])) {
            $filas[] = [
                'metrica' => 'Margen Bruto',
                'actual' => $resumen['rentabilidad']['margen_bruto'],
                'anterior' => $resumen['rentabilidad']['margen_anterior'],
                'variacion' => $resumen['rentabilidad']['variacion_margen_porcentaje'].'%',
            ];
        }

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarExcel('resumen_ejecutivo_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }

    public function exportarPdf(): mixed
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('PDF', 'Resumen Ejecutivo');
        $resumen = $this->getResumenProperty();
        $resumen['tienda_nombre'] = 'SYS-COPM';

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);
        $pdf = $exportador->generarPdf('reportes.resumen_ejecutivo_pdf', $resumen);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'resumen_ejecutivo_'.$this->fechaDesde.'_'.$this->fechaHasta.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
