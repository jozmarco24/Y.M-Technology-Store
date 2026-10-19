<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reportes;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\CompraService;
use App\Services\Reportes\ExportacionService;
use App\Services\Reportes\InventarioReporteService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventarioPage extends BaseReportePage
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Inventario';

    protected static ?string $title = 'Reporte y Analítica de Inventario';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.reportes.inventario';

    public static function permisoRequerido(): string
    {
        return 'reportes.ver_inventario';
    }

    /**
     * @return array{por_categoria: array<int, array<string, mixed>>, por_marca: array<int, array<string, mixed>>, total_general: array<string, mixed>}
     */
    public function getValorizacionProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        /** @var InventarioReporteService $service */
        $service = app(InventarioReporteService::class);

        return $service->valorizacion($usuario);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStockMuertoProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        /** @var InventarioReporteService $service */
        $service = app(InventarioReporteService::class);

        return $service->stockMuerto($usuario, 90);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReposicionSugeridaProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        /** @var InventarioReporteService $service */
        $service = app(InventarioReporteService::class);

        return $service->sugerenciaReposicion($usuario, 30, 30);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClasificacionAbcProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $desde = $this->fechaDesde ? Carbon::parse($this->fechaDesde) : null;
        $hasta = $this->fechaHasta ? Carbon::parse($this->fechaHasta) : null;

        /** @var InventarioReporteService $service */
        $service = app(InventarioReporteService::class);

        return $service->clasificacionABC($usuario, $desde, $hasta);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSeriesAntiguasProperty(): array
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        /** @var InventarioReporteService $service */
        $service = app(InventarioReporteService::class);

        return $service->seriesAntiguas($usuario, 60);
    }

    /**
     * Crea una compra en estado borrador a partir de las sugerencias de reposición.
     */
    public function crearCompraBorrador(): void
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        if (! $usuario->hasRole('admin') && ! $usuario->hasPermissionTo('compras.crear')) {
            Notification::make()->title('Sin permisos para crear compras')->danger()->send();

            return;
        }

        $sugerencias = $this->getReposicionSugeridaProperty();
        if (empty($sugerencias)) {
            Notification::make()->title('No hay productos que requieran reposición actualmente')->warning()->send();

            return;
        }

        // Proveedor predeterminado (primer proveedor activo o genérico)
        $proveedor = Proveedor::first();
        if (! $proveedor) {
            Notification::make()->title('Debe existir al menos un proveedor registrado en el sistema')->danger()->send();

            return;
        }

        $detalles = [];
        foreach ($sugerencias as $item) {
            $prod = Producto::find($item['producto_id']);
            if ($prod && $item['cantidad_sugerida'] > 0) {
                $detalles[] = [
                    'producto_id' => $prod->id,
                    'cantidad' => $item['cantidad_sugerida'],
                    'costo_unitario' => (float) $prod->precio_compra_referencial,
                ];
            }
        }

        if (empty($detalles)) {
            Notification::make()->title('No hay cantidades sugeridas mayores a cero')->warning()->send();

            return;
        }

        /** @var CompraService $compraService */
        $compraService = app(CompraService::class);

        $datosCompra = [
            'proveedor_id' => $proveedor->id,
            'tipo_comprobante' => 'factura',
            'numero_comprobante' => 'SUG-'.now()->format('YmdHis'),
            'fecha_emision' => now()->toDateString(),
            'observaciones' => 'Generada automáticamente desde Reposición Sugerida de Inventario.',
        ];

        $compra = $compraService->crearBorrador($datosCompra, $detalles, $usuario);

        Notification::make()
            ->title("Compra borrador {$compra->codigo} creada exitosamente con ".count($detalles).' productos')
            ->success()
            ->send();
    }

    public function exportarCsv(): ?StreamedResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('CSV', 'Sugerencia de Reposición');
        $sugerencias = $this->getReposicionSugeridaProperty();

        $encabezados = ['Producto', 'SKU', 'Categoría', 'Stock Actual', 'Stock Mínimo', 'Promedio Diario Ventas', 'Cobertura Actual (Días)', 'Cantidad Sugerida'];
        $filas = array_map(function ($item) {
            return [
                'producto' => $item['producto'],
                'sku' => $item['sku'],
                'categoria' => $item['categoria'],
                'stock_actual' => $item['stock_actual'],
                'stock_minimo' => $item['stock_minimo'],
                'promedio_diario' => $item['promedio_diario_ventas'],
                'cobertura_dias' => $item['dias_cobertura_actual'],
                'cantidad_sugerida' => $item['cantidad_sugerida'],
            ];
        }, $sugerencias);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarCsv('reposicion_inventario_'.now()->format('Ymd'), $encabezados, $filas);
    }

    public function exportarExcel(): ?BinaryFileResponse
    {
        if (! $this->puedeExportar()) {
            Notification::make()->title('Sin permiso para exportar')->danger()->send();

            return null;
        }

        $this->registrarActividadExportacion('Excel', 'Sugerencia de Reposición');
        $sugerencias = $this->getReposicionSugeridaProperty();

        $encabezados = ['Producto', 'SKU', 'Categoría', 'Stock Actual', 'Stock Mínimo', 'Promedio Diario Ventas', 'Cobertura Actual (Días)', 'Cantidad Sugerida'];
        $filas = array_map(function ($item) {
            return [
                'producto' => $item['producto'],
                'sku' => $item['sku'],
                'categoria' => $item['categoria'],
                'stock_actual' => $item['stock_actual'],
                'stock_minimo' => $item['stock_minimo'],
                'promedio_diario' => $item['promedio_diario_ventas'],
                'cobertura_dias' => $item['dias_cobertura_actual'],
                'cantidad_sugerida' => $item['cantidad_sugerida'],
            ];
        }, $sugerencias);

        /** @var ExportacionService $exportador */
        $exportador = app(ExportacionService::class);

        return $exportador->exportarExcel('reposicion_inventario_'.now()->format('Ymd'), $encabezados, $filas);
    }
}
