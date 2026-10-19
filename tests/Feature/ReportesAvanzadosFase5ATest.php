<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoCaja;
use App\Enums\EstadoCompra;
use App\Enums\EstadoDevolucion;
use App\Enums\EstadoSerie;
use App\Enums\MetodoPago;
use App\Enums\MotivoDevolucion;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDevolucion;
use App\Enums\TipoDocumento;
use App\Enums\TipoDocumentoCliente;
use App\Jobs\EnviarResumenSemanalEmailJob;
use App\Models\CajaSesion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Devolucion;
use App\Models\Marca;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Serie;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Services\Reportes\BaseReporteService;
use App\Services\Reportes\ComprasReporteService;
use App\Services\Reportes\ExportacionService;
use App\Services\Reportes\InventarioReporteService;
use App\Services\Reportes\PostventaReporteService;
use App\Services\Reportes\RentabilidadService;
use App\Services\Reportes\ResumenEjecutivoService;
use App\Services\Reportes\VentasReporteService;
use Carbon\Carbon;
use Database\Seeders\ConfiguracionesSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\SeriesComprobantesYClientesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportesAvanzadosFase5ATest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendedor;

    protected User $vendedor2;

    protected User $almacen;

    protected Cliente $cliente;

    protected Categoria $categoria;

    protected Marca $marca;

    protected Producto $productoA;

    protected Producto $productoB;

    protected CajaSesion $cajaSesion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->seed(ConfiguracionesSeeder::class);
        $this->seed(SeriesComprobantesYClientesSeeder::class);

        $this->admin = User::factory()->create([
            'name' => 'Admin Gerente',
            'email' => 'admin@tienda.pe',
        ]);
        $this->admin->assignRole('admin');

        $this->vendedor = User::factory()->create([
            'name' => 'Carlos Vendedor',
            'email' => 'carlos@tienda.pe',
        ]);
        $this->vendedor->assignRole('vendedor');

        $this->vendedor2 = User::factory()->create([
            'name' => 'Maria Vendedora',
            'email' => 'maria@tienda.pe',
        ]);
        $this->vendedor2->assignRole('vendedor');

        $this->almacen = User::factory()->create([
            'name' => 'Luis Almacenero',
            'email' => 'luis@tienda.pe',
        ]);
        $this->almacen->assignRole('almacen');

        $this->cliente = Cliente::firstOrCreate([
            'numero_documento' => '47895521',
        ], [
            'tipo_documento' => TipoDocumentoCliente::DNI,
            'nombre_razon_social' => 'Juan Perez',
            'direccion' => 'Av. Arequipa 1234',
        ]);

        $this->categoria = Categoria::create(['nombre' => 'Laptops', 'slug' => 'laptops']);
        $this->marca = Marca::create(['nombre' => 'Asus', 'slug' => 'asus']);

        $this->productoA = Producto::create([
            'categoria_id' => $this->categoria->id,
            'marca_id' => $this->marca->id,
            'nombre' => 'Laptop Gamer Asus TUF',
            'sku' => 'ASUS-TUF-15',
            'slug' => 'laptop-gamer-asus-tuf',
            'precio_venta' => 4000.00,
            'precio_compra_referencial' => 3000.00,
            'stock_minimo' => 2,
            'controla_series' => true,
            'activo' => true,
        ]);

        $this->productoB = Producto::create([
            'categoria_id' => $this->categoria->id,
            'marca_id' => $this->marca->id,
            'nombre' => 'Mouse Gamer Rog',
            'sku' => 'ASUS-ROG-MOUSE',
            'slug' => 'mouse-gamer-rog',
            'precio_venta' => 200.00,
            'precio_compra_referencial' => 100.00,
            'stock_minimo' => 5,
            'controla_series' => true,
            'activo' => true,
        ]);

        $this->cajaSesion = CajaSesion::create([
            'user_id' => $this->vendedor->id,
            'monto_apertura' => 500.00,
            'estado' => EstadoCaja::ABIERTA,
            'abierta_en' => Carbon::now(),
        ]);
    }

    /**
     * Auxiliar para crear una venta con detalles controlados.
     */
    protected function crearVentaManual(
        User $vendedor,
        Producto $producto,
        int $cantidad,
        float $precioUnitario,
        float $costoUnitario,
        float $descuento = 0.0,
        string $estado = 'completada',
        string $canal = 'pos',
        ?Carbon $fecha = null
    ): Venta {
        $fechaVenta = $fecha ?? Carbon::now();
        $subtotal = round(($precioUnitario * $cantidad) - $descuento, 2);
        $igv = round($subtotal * 0.18, 2);
        $total = round($subtotal + $igv, 2);

        $id = (int) (Venta::max('id') ?? 0) + 1;
        $codigo = 'VEN-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);

        $venta = Venta::create([
            'codigo' => $codigo,
            'cliente_id' => $this->cliente->id,
            'vendedor_id' => $vendedor->id,
            'caja_sesion_id' => $this->cajaSesion->id,
            'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            'serie_comprobante' => 'B001',
            'numero_comprobante' => $id,
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'igv' => $igv,
            'total' => $total,
            'estado' => $estado,
            'canal' => $canal,
        ]);

        $detalle = VentaDetalle::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'descuento' => $descuento,
            'subtotal' => $subtotal,
            'costo_unitario' => $costoUnitario,
            'descripcion' => $producto->nombre,
        ]);

        $pago = VentaPago::create([
            'venta_id' => $venta->id,
            'metodo' => MetodoPago::TARJETA,
            'monto' => $total,
        ]);

        if ($fecha !== null) {
            DB::table('ventas')->where('id', $venta->id)->update(['created_at' => $fecha, 'updated_at' => $fecha]);
            DB::table('venta_detalles')->where('id', $detalle->id)->update(['created_at' => $fecha, 'updated_at' => $fecha]);
            DB::table('venta_pagos')->where('id', $pago->id)->update(['created_at' => $fecha, 'updated_at' => $fecha]);
        }

        return $venta;
    }

    public function test_margen_por_producto_calculado_correctamente_con_descuentos_e_igv(): void
    {
        // Venta de 1 unidad: precio base 4000, descuento 200 => venta neta sin IGV = 3800.
        // Costo unitario = 3000 => margen monto = 3800 - 3000 = 800.
        // Margen % = (800 / 3800) * 100 = 21.05%.
        $this->crearVentaManual($this->vendedor, $this->productoA, 1, 4000.00, 3000.00, 200.00);

        $service = app(RentabilidadService::class);
        $resumen = $service->margenPorProducto($this->admin);

        $this->assertNotEmpty($resumen);
        $item = collect($resumen)->firstWhere('producto_id', $this->productoA->id);

        $this->assertNotNull($item);
        $this->assertEquals(3800.00, $item['venta_neta_sin_igv']);
        $this->assertEquals(3000.00, $item['costo_total']);
        $this->assertEquals(800.00, $item['margen_monto']);
        $this->assertEquals(21.05, $item['margen_porcentaje']);
    }

    public function test_venta_neta_descuenta_devoluciones_completadas(): void
    {
        $venta = $this->crearVentaManual($this->vendedor, $this->productoA, 1, 4000.00, 3000.00);
        $totalVenta = (float) $venta->total; // 4000 + 720 (IGV) = 4720.00

        // Devolución completada por 1000 soles
        Devolucion::create([
            'codigo' => 'DEV-000001',
            'venta_id' => $venta->id,
            'cliente_id' => $this->cliente->id,
            'tipo' => TipoDevolucion::DEVOLUCION_DINERO,
            'motivo' => MotivoDevolucion::DEFECTO_FABRICA,
            'detalle_motivo' => 'Pantalla con pixeles quemados',
            'estado' => EstadoDevolucion::COMPLETADA,
            'total_devuelto' => 1000.00,
            'metodo_reembolso' => MetodoPago::EFECTIVO,
            'solicitado_por' => $this->vendedor->id,
            'aprobado_por' => $this->admin->id,
            'aprobado_en' => Carbon::now(),
            'completada_en' => Carbon::now(),
        ]);

        $service = app(VentasReporteService::class);
        $metricas = $service->metricasGenerales($this->admin);

        $this->assertEquals(4720.00, $metricas['total_facturado']);
        $this->assertEquals(1000.00, $metricas['total_devoluciones']);
        $this->assertEquals(3720.00, $metricas['venta_neta']);
    }

    public function test_ventas_anuladas_no_cuentan_en_reportes(): void
    {
        $this->crearVentaManual($this->vendedor, $this->productoA, 1, 4000.00, 3000.00, 0.0, 'anulada');

        $service = app(VentasReporteService::class);
        $metricas = $service->metricasGenerales($this->admin);

        $this->assertEquals(0, $metricas['total_pedidos']);
        $this->assertEquals(0.00, $metricas['total_facturado']);
        $this->assertEquals(0.00, $metricas['venta_neta']);
    }

    public function test_stock_muerto_detecta_productos_sin_ventas_en_n_dias(): void
    {
        // Producto A tiene series disponibles pero su última venta fue hace 100 días
        Serie::create([
            'producto_id' => $this->productoA->id,
            'numero_serie' => 'SN-ASUS-100',
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => 3000.00,
            'fecha_ingreso' => Carbon::now()->subDays(120),
        ]);

        $this->crearVentaManual($this->vendedor, $this->productoA, 1, 4000.00, 3000.00, 0.0, 'completada', 'pos', Carbon::now()->subDays(100));

        // Producto B tiene serie y vendió ayer
        Serie::create([
            'producto_id' => $this->productoB->id,
            'numero_serie' => 'SN-MOUSE-001',
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => 100.00,
            'fecha_ingreso' => Carbon::now()->subDays(10),
        ]);
        $this->crearVentaManual($this->vendedor, $this->productoB, 1, 200.00, 100.00, 0.0, 'completada', 'pos', Carbon::now()->subDay());

        $service = app(InventarioReporteService::class);
        $stockMuerto = $service->stockMuerto($this->admin, 90);

        $productoIds = collect($stockMuerto)->pluck('producto_id')->toArray();

        $this->assertContains($this->productoA->id, $productoIds);
        $this->assertNotContains($this->productoB->id, $productoIds);
    }

    public function test_rotacion_y_cobertura_calculadas_correctamente(): void
    {
        // 5 unidades en stock
        for ($i = 1; $i <= 5; $i++) {
            Serie::create([
                'producto_id' => $this->productoA->id,
                'numero_serie' => "SN-ROT-{$i}",
                'estado' => EstadoSerie::DISPONIBLE,
                'costo' => 3000.00,
                'fecha_ingreso' => Carbon::now()->subDays(20),
            ]);
        }

        // 10 unidades vendidas en los últimos 30 días
        $this->crearVentaManual($this->vendedor, $this->productoA, 10, 4000.00, 3000.00, 0.0, 'completada', 'pos', Carbon::now()->subDays(5));

        $service = app(InventarioReporteService::class);
        $rotacion = $service->rotacion($this->admin, Carbon::now()->subDays(30), Carbon::now());

        $item = collect($rotacion)->firstWhere('producto_id', $this->productoA->id);
        $this->assertNotNull($item);
        $this->assertEquals(10, $item['unidades_vendidas']);
        $this->assertEquals(5, $item['stock_actual']);
        $this->assertGreaterThan(0, $item['indice_rotacion']);
        $this->assertLessThan(999, $item['dias_inventario']);
    }

    public function test_sugerencia_reposicion_respeta_cobertura_y_stock_minimo(): void
    {
        // Producto A: stock_minimo = 2.
        // Stock actual disponible = 1 unidad.
        Serie::create([
            'producto_id' => $this->productoA->id,
            'numero_serie' => 'SN-REP-01',
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => 3000.00,
            'fecha_ingreso' => Carbon::now()->subDays(15),
        ]);

        // Vendió 30 unidades en los últimos 30 días => Promedio = 1.0 por día.
        $this->crearVentaManual($this->vendedor, $this->productoA, 30, 4000.00, 3000.00, 0.0, 'completada', 'pos', Carbon::now()->subDays(2));

        $service = app(InventarioReporteService::class);
        // Cobertura deseada de 30 días: Demanda esperada = 30 * 1 = 30.
        // Sugerido = (30 demanda + 2 stock_minimo) - 1 stock actual = 31 unidades.
        $reposicion = $service->sugerenciaReposicion($this->admin, 30, 30);

        $item = collect($reposicion)->firstWhere('producto_id', $this->productoA->id);
        $this->assertNotNull($item);
        $this->assertEquals(31, $item['cantidad_sugerida']);
        $this->assertTrue($item['requiere_reposicion']);
    }

    public function test_clasificacion_abc_correcta(): void
    {
        // Producto A genera margen alto (10 unidades * 1000 = 10000 margen)
        $this->crearVentaManual($this->vendedor, $this->productoA, 10, 4000.00, 3000.00);

        // Producto B genera margen menor (1 unidad * 100 = 100 margen)
        $this->crearVentaManual($this->vendedor, $this->productoB, 1, 200.00, 100.00);

        $service = app(InventarioReporteService::class);
        $abc = $service->clasificacionABC($this->admin);

        $itemA = collect($abc)->firstWhere('producto_id', $this->productoA->id);
        $itemB = collect($abc)->firstWhere('producto_id', $this->productoB->id);

        $this->assertNotNull($itemA);
        $this->assertNotNull($itemB);
        $this->assertEquals('A', $itemA['clasificacion']);
    }

    public function test_evolucion_del_costo_refleja_varias_compras(): void
    {
        $proveedor = Proveedor::create([
            'tipo_documento' => TipoDocumento::RUC,
            'numero_documento' => '20556677889',
            'razon_social' => 'Mayorista Tech SAC',
            'email' => 'tech@mayorista.pe',
            'telefono' => '999888777',
            'direccion' => 'Av. Central 555',
        ]);

        $compra1 = Compra::create([
            'codigo' => 'COM-000010',
            'proveedor_id' => $proveedor->id,
            'tipo_comprobante' => 'factura',
            'numero_comprobante' => 'F001-0001',
            'fecha_emision' => Carbon::now()->subMonths(2)->toDateString(),
            'estado' => EstadoCompra::RECIBIDA,
            'subtotal' => 2500.00,
            'igv' => 450.00,
            'total' => 2950.00,
            'creado_por' => $this->admin->id,
        ]);
        DB::table('compras')->where('id', $compra1->id)->update(['created_at' => Carbon::now()->subMonths(2)]);

        CompraDetalle::create([
            'compra_id' => $compra1->id,
            'producto_id' => $this->productoA->id,
            'cantidad' => 1,
            'costo_unitario' => 2500.00,
            'subtotal' => 2500.00,
        ]);

        $compra2 = Compra::create([
            'codigo' => 'COM-000011',
            'proveedor_id' => $proveedor->id,
            'tipo_comprobante' => 'factura',
            'numero_comprobante' => 'F001-0002',
            'fecha_emision' => Carbon::now()->subMonth()->toDateString(),
            'estado' => EstadoCompra::RECIBIDA,
            'subtotal' => 3000.00,
            'igv' => 540.00,
            'total' => 3540.00,
            'creado_por' => $this->admin->id,
        ]);
        DB::table('compras')->where('id', $compra2->id)->update(['created_at' => Carbon::now()->subMonth()]);

        CompraDetalle::create([
            'compra_id' => $compra2->id,
            'producto_id' => $this->productoA->id,
            'cantidad' => 1,
            'costo_unitario' => 3000.00,
            'subtotal' => 3000.00,
        ]);

        $service = app(ComprasReporteService::class);
        $evolucion = $service->evolucionCostoProducto($this->admin, $this->productoA->id);

        $this->assertCount(2, $evolucion);
        $this->assertEquals(2500.00, $evolucion[0]['costo_unitario']);
        $this->assertEquals(3000.00, $evolucion[1]['costo_unitario']);
        // Variación = ((3000 - 2500) / 2500) * 100 = +20%
        $this->assertEquals(20.00, $evolucion[1]['variacion_porcentaje']);
    }

    public function test_tasa_de_fallas_por_modelo_correcta(): void
    {
        // Vendidas 10 unidades
        $this->crearVentaManual($this->vendedor, $this->productoA, 10, 4000.00, 3000.00);

        // 2 órdenes de servicio registradas para el producto A
        for ($i = 1; $i <= 2; $i++) {
            OrdenServicio::create([
                'codigo' => "OS-00009{$i}",
                'tipo' => 'garantia',
                'producto_id' => $this->productoA->id,
                'cliente_id' => $this->cliente->id,
                'falla_reportada' => 'No enciende',
                'estado_recepcion' => 'Buen estado',
                'estado' => 'recibido',
                'recibido_por' => $this->admin->id,
                'recibido_en' => Carbon::now(),
            ]);
        }

        $service = app(PostventaReporteService::class);
        $fallas = $service->tasaFallas($this->admin);

        $item = collect($fallas)->firstWhere('producto_id', $this->productoA->id);
        $this->assertNotNull($item);
        $this->assertEquals(10, $item['unidades_vendidas']);
        $this->assertEquals(2, $item['ordenes_servicio']);
        // Tasa = (2 / 10) * 100 = 20%
        $this->assertEquals(20.00, $item['tasa_fallas_porcentaje']);
    }

    public function test_vendedor_y_almacen_no_reciben_costos_ni_margenes(): void
    {
        $this->crearVentaManual($this->vendedor, $this->productoA, 2, 4000.00, 3000.00);

        $serviceRentabilidad = app(RentabilidadService::class);
        $resumenVendedor = $serviceRentabilidad->margenPorProducto($this->vendedor);
        $itemVendedor = collect($resumenVendedor)->firstWhere('producto_id', $this->productoA->id);

        $this->assertNotNull($itemVendedor);
        $this->assertArrayNotHasKey('costo_total', $itemVendedor);
        $this->assertArrayNotHasKey('margen_monto', $itemVendedor);
        $this->assertArrayNotHasKey('margen_porcentaje', $itemVendedor);

        // Almacén tampoco ve costos ni márgenes en rentabilidad
        $resumenAlmacen = $serviceRentabilidad->margenPorProducto($this->almacen);
        $itemAlmacen = collect($resumenAlmacen)->firstWhere('producto_id', $this->productoA->id);
        $this->assertNotNull($itemAlmacen);
        $this->assertArrayNotHasKey('costo_total', $itemAlmacen);
        $this->assertArrayNotHasKey('margen_monto', $itemAlmacen);
    }

    public function test_vendedor_solo_ve_sus_propias_ventas(): void
    {
        // Venta de vendedor 1
        $this->crearVentaManual($this->vendedor, $this->productoA, 1, 4000.00, 3000.00);
        // Venta de vendedor 2
        $this->crearVentaManual($this->vendedor2, $this->productoB, 3, 200.00, 100.00);

        $service = app(VentasReporteService::class);
        $metricasVendedor1 = $service->metricasGenerales($this->vendedor);

        // Vendedor 1 solo debe ver 1 pedido de su autoría
        $this->assertEquals(1, $metricasVendedor1['total_pedidos']);

        // Admin debe ver los 2 pedidos
        $metricasAdmin = $service->metricasGenerales($this->admin);
        $this->assertEquals(2, $metricasAdmin['total_pedidos']);
    }

    public function test_cache_se_invalida_al_registrar_una_venta(): void
    {
        $versionInicial = BaseReporteService::obtenerCacheVersion();

        BaseReporteService::invalidarCache();
        $versionSiguiente = BaseReporteService::obtenerCacheVersion();

        $this->assertEquals($versionInicial + 1, $versionSiguiente);
    }

    public function test_comando_resumen_semanal_encola_el_correo_y_respeta_destinatarios(): void
    {
        Queue::fake();

        $this->artisan('reportes:resumen-semanal --destinatarios=gerente@tienda.pe,contador@tienda.pe')
            ->assertSuccessful();

        Queue::assertPushed(EnviarResumenSemanalEmailJob::class, function ($job) {
            return in_array('gerente@tienda.pe', $job->destinatarios, true)
                && in_array('contador@tienda.pe', $job->destinatarios, true);
        });
    }

    public function test_exportacion_genera_archivo_csv_y_pdf_valido(): void
    {
        $exportador = app(ExportacionService::class);

        // 1. CSV
        $encabezados = ['Producto', 'SKU', 'Venta'];
        $filas = [
            ['producto' => 'Laptop Gamer', 'sku' => 'SKU-001', 'venta' => 4500.00],
        ];

        $csvResponse = $exportador->exportarCsv('test_reporte', $encabezados, $filas);
        $this->assertEquals(200, $csvResponse->getStatusCode());
        $this->assertStringContainsString('text/csv', (string) $csvResponse->headers->get('Content-Type'));

        // 2. PDF
        $resumenService = app(ResumenEjecutivoService::class);
        $datos = $resumenService->generar($this->admin);
        $datos['tienda_nombre'] = 'SYS-COPM';

        $pdf = $exportador->generarPdf('reportes.resumen_ejecutivo_pdf', $datos);
        $pdfOutput = $pdf->output();

        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF', $pdfOutput);
    }
}
