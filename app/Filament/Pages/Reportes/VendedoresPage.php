<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reportes;

use App\Models\Configuracion;
use App\Models\User;
use App\Services\Reportes\ExportacionService;
use App\Services\Reportes\VendedorReporteService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendedoresPage extends BaseReportePage
{
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Vendedores';

    protected static ?string $title = 'Ranking y Rendimiento de Vendedores';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.reportes.vendedores';

    public static function permisoRequerido(): string
    {
        return 'reportes.ver';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRankingProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var VendedorReporteService $service */
        $service = app(VendedorReporteService::class);

        return $service->ranking($usuario, $desde, $hasta);
    }

    public function estanComisionesActivas(): bool
    {
        return (bool) (int) Configuracion::obtener('activar_comisiones', '0');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getComisionesProperty(): array
    {
        if (! $this->estanComisionesActivas() || ! $this->puedeVerMargen()) {
            return [];
        }

        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var VendedorReporteService $service */
        $service = app(VendedorReporteService::class);

        return $service->calcularComisiones($usuario, $desde, $hasta);
    }

    public function exportarCsv(): ?StreamedResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('CSV', 'Ranking de Vendedores');
        $ranking = $this->getRankingProperty();

        $encabezados = ['Vendedor', 'Pedidos', 'Total Vendido (S/)', 'Ticket Promedio (S/)', 'Descuentos (S/)', 'Anulaciones'];
        if ($this->puedeVerMargen()) {
            $encabezados[] = 'Margen (S/)';
            $encabezados[] = 'Margen (%)';
        }

        $filas = array_map(function ($item) {
            $row = [
                'vendedor' => $item['vendedor'],
                'pedidos' => $item['total_pedidos'],
                'total_vendido' => $item['total_vendido'],
                'ticket_promedio' => $item['ticket_promedio'],
                'descuentos' => $item['descuentos_otorgados'],
                'anulaciones' => $item['ventas_anuladas'],
            ];

            if ($this->puedeVerMargen()) {
                $row['margen'] = $item['margen_total'] ?? 0;
                $row['margen_porcentaje'] = ($item['margen_porcentaje'] ?? 0).'%';
            }

            return $row;
        }, $ranking);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarCsv('ranking_vendedores_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }

    public function exportarExcel(): ?BinaryFileResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('Excel', 'Ranking de Vendedores');
        $ranking = $this->getRankingProperty();

        $encabezados = ['Vendedor', 'Pedidos', 'Total Vendido (S/)', 'Ticket Promedio (S/)', 'Descuentos (S/)', 'Anulaciones'];
        if ($this->puedeVerMargen()) {
            $encabezados[] = 'Margen (S/)';
            $encabezados[] = 'Margen (%)';
        }

        $filas = array_map(function ($item) {
            $row = [
                'vendedor' => $item['vendedor'],
                'pedidos' => $item['total_pedidos'],
                'total_vendido' => $item['total_vendido'],
                'ticket_promedio' => $item['ticket_promedio'],
                'descuentos' => $item['descuentos_otorgados'],
                'anulaciones' => $item['ventas_anuladas'],
            ];

            if ($this->puedeVerMargen()) {
                $row['margen'] = $item['margen_total'] ?? 0;
                $row['margen_porcentaje'] = $item['margen_porcentaje'] ?? 0;
            }

            return $row;
        }, $ranking);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarExcel('ranking_vendedores_'.$this->fechaDesde.'_'.$this->fechaHasta, $encabezados, $filas);
    }
}
