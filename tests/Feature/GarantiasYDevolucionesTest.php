<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoDevolucion;
use App\Enums\EstadoFisico;
use App\Enums\EstadoGarantia;
use App\Enums\EstadoSerie;
use App\Enums\EstadoVenta;
use App\Enums\MetodoPago;
use App\Enums\MotivoDevolucion;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDevolucion;
use App\Enums\TipoDocumentoCliente;
use App\Enums\TipoMovimiento;
use App\Models\CajaSesion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Garantia;
use App\Models\Marca;
use App\Models\MovimientoInventario;
use App\Models\NotaCredito;
use App\Models\PoliticaGarantia;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use App\Models\Venta;
use App\Services\CajaService;
use App\Services\CambioService;
use App\Services\DevolucionService;
use App\Services\GarantiaService;
use App\Services\VentaService;
use Carbon\Carbon;
use Database\Seeders\ConfiguracionesSeeder;
use Database\Seeders\PoliticaGarantiaSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\SeriesComprobantesYClientesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class GarantiasYDevolucionesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendedor;

    protected Cliente $clienteDni;

    protected Cliente $clienteRuc;

    protected Categoria $categoriaLaptops;

    protected Categoria $categoriaAccesorios;

    protected Producto $productoLaptop;

    protected Producto $productoLaptop2;

    protected Producto $productoMouse;

    protected VentaService $ventaService;

    protected CajaService $cajaService;

    protected GarantiaService $garantiaService;

    protected DevolucionService $devolucionService;

    protected CambioService $cambioService;

    protected CajaSesion $cajaVendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->seed(SeriesComprobantesYClientesSeeder::class);
        $this->seed(ConfiguracionesSeeder::class);
        $this->seed(PoliticaGarantiaSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin Test', 'email' => 'admin@test.com']);
        $this->admin->assignRole('admin');

        $this->vendedor = User::factory()->create(['name' => 'Vendedor Test', 'email' => 'vendedor@test.com']);
        $this->vendedor->assignRole('vendedor');

        $this->clienteDni = Cliente::create([
            'tipo_documento' => TipoDocumentoCliente::DNI,
            'numero_documento' => '45678912',
            'nombre_razon_social' => 'JUAN PEREZ',
            'email' => 'juan@test.com',
        ]);

        $this->clienteRuc = Cliente::create([
            'tipo_documento' => TipoDocumentoCliente::RUC,
            'numero_documento' => '20609998881',
            'nombre_razon_social' => 'CORPORACION ABC S.A.C.',
            'direccion' => 'Av. Javier Prado 456',
            'email' => 'abc@empresa.com',
        ]);

        $this->categoriaLaptops = Categoria::create(['nombre' => 'Laptops', 'slug' => 'laptops']);
        $this->categoriaAccesorios = Categoria::create(['nombre' => 'Accesorios', 'slug' => 'accesorios']);
        $marca = Marca::create(['nombre' => 'Asus', 'slug' => 'asus']);

        $this->productoLaptop = Producto::create([
            'categoria_id' => $this->categoriaLaptops->id,
            'marca_id' => $marca->id,
            'nombre' => 'Asus ZenBook 14',
            'slug' => 'asus-zenbook-14',
            'sku' => 'ASUS-ZB-001',
            'controla_series' => true,
            'precio_venta' => 4000.00,
            'precio_minimo' => 3800.00,
            'precio_compra_referencial' => 3000.00,
            'stock_minimo' => 2,
            'activo' => true,
        ]);

        $this->productoLaptop2 = Producto::create([
            'categoria_id' => $this->categoriaLaptops->id,
            'marca_id' => $marca->id,
            'nombre' => 'Asus ROG Strix',
            'slug' => 'asus-rog-strix',
            'sku' => 'ASUS-ROG-001',
            'controla_series' => true,
            'precio_venta' => 5000.00,
            'precio_minimo' => 4800.00,
            'precio_compra_referencial' => 4000.00,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $this->productoMouse = Producto::create([
            'categoria_id' => $this->categoriaAccesorios->id,
            'marca_id' => $marca->id,
            'nombre' => 'Mouse Asus Gamer',
            'slug' => 'mouse-asus-gamer',
            'sku' => 'MS-ASUS-001',
            'controla_series' => false,
            'precio_venta' => 100.00,
            'precio_minimo' => 80.00,
            'precio_compra_referencial' => 50.00,
            'stock_minimo' => 5,
            'activo' => true,
        ]);

        $this->ventaService = app(VentaService::class);
        $this->cajaService = app(CajaService::class);
        $this->garantiaService = app(GarantiaService::class);
        $this->devolucionService = app(DevolucionService::class);
        $this->cambioService = app(CambioService::class);

        $this->cajaVendedor = $this->cajaService->abrir($this->vendedor, 300.00);
    }

    private function crearSerie(Producto $producto, string $numero): Serie
    {
        return Serie::create([
            'producto_id' => $producto->id,
            'numero_serie' => $numero,
            'costo' => $producto->precio_compra_referencial,
            'estado' => EstadoSerie::DISPONIBLE,
            'fecha_ingreso' => now(),
        ]);
    }

    public function test_vender_crea_garantia_por_serie_con_la_politica_correcta_prioridad(): void
    {
        // 1. Política por categoría: 24 meses, 15 días cambio
        PoliticaGarantia::create([
            'nombre' => 'Garantía Laptops Categoría',
            'categoria_id' => $this->categoriaLaptops->id,
            'producto_id' => null,
            'meses_garantia' => 24,
            'dias_cambio' => 15,
            'cubre' => 'Hardware laptops.',
            'activo' => true,
        ]);

        // 2. Política por producto (más específica): 36 meses, 30 días cambio
        PoliticaGarantia::create([
            'nombre' => 'Garantía Específica Asus Zenbook',
            'categoria_id' => null,
            'producto_id' => $this->productoLaptop->id,
            'meses_garantia' => 36,
            'dias_cambio' => 30,
            'cubre' => 'Pantalla OLED y placa madre.',
            'activo' => true,
        ]);

        $serie = $this->crearSerie($this->productoLaptop, 'SN-ZENBOOK-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoLaptop->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00],
            ],
            vendedor: $this->vendedor
        );

        $garantia = Garantia::where('serie_id', $serie->id)->first();
        $this->assertNotNull($garantia);
        $this->assertEquals(EstadoGarantia::VIGENTE, $garantia->estado);
        // Debe ganar la política por producto (36 meses y 30 días)
        $this->assertEquals(36, $garantia->fecha_inicio->diffInMonths($garantia->fecha_fin));
        $this->assertEquals(30, $garantia->fecha_inicio->diffInDays($garantia->dias_cambio_hasta));
    }

    public function test_snapshot_de_condiciones_se_conserva_aunque_cambie_la_politica_despues(): void
    {
        $serie = $this->crearSerie($this->productoLaptop, 'SN-SNAPSHOT-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoLaptop->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00],
            ],
            vendedor: $this->vendedor
        );

        $garantia = Garantia::where('serie_id', $serie->id)->first();
        $snapshotOriginal = $garantia->condiciones_snapshot;

        // Modificar la política general en base de datos
        $politicaGeneral = PoliticaGarantia::whereNull('categoria_id')->whereNull('producto_id')->first();
        $politicaGeneral->update([
            'meses_garantia' => 6,
            'cubre' => 'TEXTO COMPLETAMENTE MODIFICADO POSTERIORMENTE',
        ]);

        $this->assertEquals($snapshotOriginal, $garantia->fresh()->condiciones_snapshot);
        $this->assertStringContainsString('12 meses', $garantia->fresh()->condiciones_snapshot);
    }

    public function test_consultar_garantia_por_serie_devuelve_dias_restantes_correctos(): void
    {
        $serie = $this->crearSerie($this->productoLaptop, 'SN-CONSULTA-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoLaptop->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00],
            ],
            vendedor: $this->vendedor
        );

        $consulta = $this->garantiaService->consultarPorSerie('sn-consulta-001');

        $this->assertTrue($consulta['encontrado']);
        $this->assertTrue($consulta['vigente']);
        $this->assertGreaterThan(300, $consulta['dias_restantes']);
        $this->assertTrue($consulta['en_plazo_cambio']);
        $this->assertEquals($venta->codigo, $consulta['venta_codigo']);
    }

    public function test_garantia_vencida_se_marca_por_el_proceso_masivo(): void
    {
        $serie = $this->crearSerie($this->productoLaptop, 'SN-VENCER-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoLaptop->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00],
            ],
            vendedor: $this->vendedor
        );

        $garantia = Garantia::where('serie_id', $serie->id)->first();
        // Simular que la fecha de fin ya expiró
        $garantia->update(['fecha_fin' => Carbon::yesterday()]);

        $this->garantiaService->marcarVencidas();

        $this->assertEquals(EstadoGarantia::VENCIDA, $garantia->fresh()->estado);
    }

    public function test_devolucion_fuera_de_plazo_es_rechazada_salvo_defecto_dentro_de_garantia(): void
    {
        $serie = $this->crearSerie($this->productoLaptop, 'SN-PLAZO-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoLaptop->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00],
            ],
            vendedor: $this->vendedor
        );

        $detalle = $venta->detalles()->first();
        $garantia = Garantia::where('serie_id', $serie->id)->first();
        // Plazo de cambio venció hace 2 días, pero garantía sigue vigente por 11 meses
        $garantia->update(['dias_cambio_hasta' => Carbon::today()->subDays(2)]);

        // Intento de devolución por arrepentimiento por parte de vendedor -> Debe fallar
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El plazo de cambio/devolución para la serie venció');

        $this->devolucionService->solicitar(
            venta: $venta,
            items: [
                [
                    'venta_detalle_id' => $detalle->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'estado_fisico' => EstadoFisico::ABIERTO_OK,
                ],
            ],
            tipo: TipoDevolucion::DEVOLUCION_DINERO,
            motivo: MotivoDevolucion::ARREPENTIMIENTO,
            detalleMotivo: 'Cliente no le gustó el color',
            solicitante: $this->vendedor
        );
    }

    public function test_devolucion_fuera_de_plazo_cambio_si_procede_por_defecto_de_fabrica_en_garantia(): void
    {
        $serie = $this->crearSerie($this->productoLaptop, 'SN-DEFECTO-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoLaptop->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00],
            ],
            vendedor: $this->vendedor
        );

        $detalle = $venta->detalles()->first();
        $garantia = Garantia::where('serie_id', $serie->id)->first();
        $garantia->update(['dias_cambio_hasta' => Carbon::today()->subDays(10)]);

        // Motivo defecto_fabrica -> Debe admitirse la solicitud
        $devolucion = $this->devolucionService->solicitar(
            venta: $venta,
            items: [
                [
                    'venta_detalle_id' => $detalle->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'estado_fisico' => EstadoFisico::CON_DEFECTO,
                ],
            ],
            tipo: TipoDevolucion::CAMBIO_PRODUCTO,
            motivo: MotivoDevolucion::DEFECTO_FABRICA,
            detalleMotivo: 'No enciende la pantalla',
            solicitante: $this->vendedor
        );

        $this->assertEquals(EstadoDevolucion::SOLICITADA, $devolucion->estado);
    }

    public function test_no_se_devuelve_dos_veces_la_misma_serie(): void
    {
        $serie = $this->crearSerie($this->productoLaptop, 'SN-DURABLE-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoLaptop->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00],
            ],
            vendedor: $this->vendedor
        );

        $detalle = $venta->detalles()->first();

        // Primera solicitud
        $this->devolucionService->solicitar(
            venta: $venta,
            items: [
                ['venta_detalle_id' => $detalle->id, 'serie_id' => $serie->id, 'cantidad' => 1, 'estado_fisico' => EstadoFisico::NUEVO_SELLADO],
            ],
            tipo: TipoDevolucion::DEVOLUCION_DINERO,
            motivo: MotivoDevolucion::ARREPENTIMIENTO,
            detalleMotivo: 'Devolución normal',
            solicitante: $this->vendedor
        );

        // Segunda solicitud con la misma serie debe ser rechazada
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ya tiene una devolución registrada');

        $this->devolucionService->solicitar(
            venta: $venta,
            items: [
                ['venta_detalle_id' => $detalle->id, 'serie_id' => $serie->id, 'cantidad' => 1, 'estado_fisico' => EstadoFisico::NUEVO_SELLADO],
            ],
            tipo: TipoDevolucion::DEVOLUCION_DINERO,
            motivo: MotivoDevolucion::ARREPENTIMIENTO,
            detalleMotivo: 'Intento duplicado',
            solicitante: $this->vendedor
        );
    }

    public function test_completar_devolucion_dinero_registra_egreso_en_caja_y_sin_caja_abierta_falla(): void
    {
        $serie = $this->crearSerie($this->productoLaptop, 'SN-CAJA-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoLaptop->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00],
            ],
            vendedor: $this->vendedor
        );

        $detalle = $venta->detalles()->first();

        $devolucion = $this->devolucionService->solicitar(
            venta: $venta,
            items: [
                ['venta_detalle_id' => $detalle->id, 'serie_id' => $serie->id, 'cantidad' => 1, 'estado_fisico' => EstadoFisico::NUEVO_SELLADO],
            ],
            tipo: TipoDevolucion::DEVOLUCION_DINERO,
            motivo: MotivoDevolucion::ARREPENTIMIENTO,
            detalleMotivo: 'Devolución dentro de los 7 días',
            solicitante: $this->vendedor,
            metodoReembolso: 'efectivo'
        );

        // Admin aprueba
        $this->devolucionService->aprobar($devolucion, $this->admin);

        // Si el admin no tiene caja abierta y trata de completar como admin -> Debe fallar
        try {
            $this->devolucionService->completar($devolucion, $this->admin);
            $this->fail('Debió fallar porque el admin no tiene caja abierta para egresar el dinero');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tenga una caja abierta', $e->getMessage());
        }

        // El vendedor sí tiene caja abierta (300 inicial + 4000 venta = 4300)
        $completada = $this->devolucionService->completar($devolucion, $this->vendedor);

        $this->assertEquals(EstadoDevolucion::COMPLETADA, $completada->estado);
        $this->assertDatabaseHas('cajas_movimientos', [
            'caja_sesion_id' => $this->cajaVendedor->id,
            'tipo' => 'egreso',
            'monto' => 4000.00,
        ]);
        $this->assertEquals(EstadoSerie::DISPONIBLE, $serie->fresh()->estado);
    }

    public function test_destino_de_serie_segun_estado_fisico_y_movimientos_registrados(): void
    {
        // 1. Serie con defecto -> destino en_garantia
        $serie = $this->crearSerie($this->productoLaptop, 'SN-DEST-DEF-001');

        $venta = $this->ventaService->registrar(
            datos: ['cliente_id' => $this->clienteDni->id, 'tipo_comprobante' => TipoComprobanteVenta::BOLETA],
            items: [['producto_id' => $this->productoLaptop->id, 'serie_id' => $serie->id, 'cantidad' => 1]],
            pagos: [['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00]],
            vendedor: $this->vendedor
        );

        $detalle = $venta->detalles()->first();

        $devolucion = $this->devolucionService->solicitar(
            venta: $venta,
            items: [
                [
                    'venta_detalle_id' => $detalle->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'estado_fisico' => EstadoFisico::CON_DEFECTO,
                    'destino_serie' => 'en_garantia',
                ],
            ],
            tipo: TipoDevolucion::DEVOLUCION_DINERO,
            motivo: MotivoDevolucion::DEFECTO_FABRICA,
            detalleMotivo: 'Falla de placa',
            solicitante: $this->vendedor,
            metodoReembolso: 'efectivo'
        );

        $this->devolucionService->aprobar($devolucion, $this->admin);
        $this->devolucionService->completar($devolucion, $this->vendedor);

        $this->assertEquals(EstadoSerie::EN_GARANTIA, $serie->fresh()->estado);
        $this->assertEquals(EstadoGarantia::ANULADA, Garantia::where('serie_id', $serie->id)->first()->estado);
    }

    public function test_nota_de_credito_solo_para_boleta_y_factura_nota_venta_no_la_genera(): void
    {
        $serie = $this->crearSerie($this->productoLaptop, 'SN-NV-NC-001');

        $ventaNV = $this->ventaService->registrar(
            datos: ['cliente_id' => $this->clienteDni->id, 'tipo_comprobante' => TipoComprobanteVenta::NOTA_VENTA],
            items: [['producto_id' => $this->productoLaptop->id, 'serie_id' => $serie->id, 'cantidad' => 1]],
            pagos: [['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00]],
            vendedor: $this->vendedor
        );

        $detalleNV = $ventaNV->detalles()->first();

        $devNV = $this->devolucionService->solicitar(
            venta: $ventaNV,
            items: [['venta_detalle_id' => $detalleNV->id, 'serie_id' => $serie->id, 'cantidad' => 1, 'estado_fisico' => EstadoFisico::NUEVO_SELLADO]],
            tipo: TipoDevolucion::NOTA_CREDITO,
            motivo: MotivoDevolucion::ARREPENTIMIENTO,
            detalleMotivo: 'Prueba NC en Nota de Venta',
            solicitante: $this->vendedor
        );

        $this->devolucionService->aprobar($devNV, $this->admin);

        // Completar devolución con NotaCredito en Nota de Venta debe ser rechazada
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Las notas de venta no pueden generar notas de crédito electrónicas');

        $this->devolucionService->completar($devNV, $this->vendedor);
    }

    public function test_cambio_de_serie_nueva_serie_vendida_nueva_garantia_original_con_su_destino(): void
    {
        $serieOriginal = $this->crearSerie($this->productoLaptop, 'SN-ORIG-001');
        $serieNueva = $this->crearSerie($this->productoLaptop, 'SN-NUEVA-001');

        $venta = $this->ventaService->registrar(
            datos: ['cliente_id' => $this->clienteDni->id, 'tipo_comprobante' => TipoComprobanteVenta::BOLETA],
            items: [['producto_id' => $this->productoLaptop->id, 'serie_id' => $serieOriginal->id, 'cantidad' => 1]],
            pagos: [['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00]],
            vendedor: $this->vendedor
        );

        $detalle = $venta->detalles()->first();

        $devolucion = $this->devolucionService->solicitar(
            venta: $venta,
            items: [
                [
                    'venta_detalle_id' => $detalle->id,
                    'serie_id' => $serieOriginal->id,
                    'cantidad' => 1,
                    'estado_fisico' => EstadoFisico::CON_DEFECTO,
                    'destino_serie' => 'en_garantia',
                ],
            ],
            tipo: TipoDevolucion::CAMBIO_PRODUCTO,
            motivo: MotivoDevolucion::DEFECTO_FABRICA,
            detalleMotivo: 'Pantalla azul recurrente',
            solicitante: $this->vendedor
        );

        $this->devolucionService->aprobar($devolucion, $this->admin);
        $this->devolucionService->completar($devolucion, $this->vendedor, $serieNueva->id);

        $this->assertEquals(EstadoSerie::EN_GARANTIA, $serieOriginal->fresh()->estado);
        $this->assertEquals(EstadoSerie::VENDIDA, $serieNueva->fresh()->estado);

        // Garantía original anulada
        $this->assertEquals(EstadoGarantia::ANULADA, Garantia::where('serie_id', $serieOriginal->id)->first()->estado);

        // Nueva garantía creada y vigente para la nueva serie
        $garantiaNueva = Garantia::where('serie_id', $serieNueva->id)->first();
        $this->assertNotNull($garantiaNueva);
        $this->assertTrue($garantiaNueva->esVigente());
    }

    public function test_dos_cambios_simultaneos_con_la_misma_serie_nueva_solo_uno_gana(): void
    {
        $serieOriginal1 = $this->crearSerie($this->productoLaptop, 'SN-ORIG-A1');
        $serieOriginal2 = $this->crearSerie($this->productoLaptop, 'SN-ORIG-A2');
        $serieUnicaNueva = $this->crearSerie($this->productoLaptop, 'SN-UNICA-NUEVA');

        $venta1 = $this->ventaService->registrar(
            datos: ['cliente_id' => $this->clienteDni->id, 'tipo_comprobante' => TipoComprobanteVenta::BOLETA],
            items: [['producto_id' => $this->productoLaptop->id, 'serie_id' => $serieOriginal1->id, 'cantidad' => 1]],
            pagos: [['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00]],
            vendedor: $this->vendedor
        );

        $venta2 = $this->ventaService->registrar(
            datos: ['cliente_id' => $this->clienteDni->id, 'tipo_comprobante' => TipoComprobanteVenta::BOLETA],
            items: [['producto_id' => $this->productoLaptop->id, 'serie_id' => $serieOriginal2->id, 'cantidad' => 1]],
            pagos: [['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00]],
            vendedor: $this->vendedor
        );

        $dev1 = $this->devolucionService->solicitar(
            venta: $venta1,
            items: [['venta_detalle_id' => $venta1->detalles()->first()->id, 'serie_id' => $serieOriginal1->id, 'cantidad' => 1, 'estado_fisico' => EstadoFisico::CON_DEFECTO]],
            tipo: TipoDevolucion::CAMBIO_PRODUCTO,
            motivo: MotivoDevolucion::DEFECTO_FABRICA,
            detalleMotivo: 'Falla 1',
            solicitante: $this->vendedor
        );

        $dev2 = $this->devolucionService->solicitar(
            venta: $venta2,
            items: [['venta_detalle_id' => $venta2->detalles()->first()->id, 'serie_id' => $serieOriginal2->id, 'cantidad' => 1, 'estado_fisico' => EstadoFisico::CON_DEFECTO]],
            tipo: TipoDevolucion::CAMBIO_PRODUCTO,
            motivo: MotivoDevolucion::DEFECTO_FABRICA,
            detalleMotivo: 'Falla 2',
            solicitante: $this->vendedor
        );

        $this->devolucionService->aprobar($dev1, $this->admin);
        $this->devolucionService->aprobar($dev2, $this->admin);

        // Primer cambio gana
        $this->devolucionService->completar($dev1, $this->vendedor, $serieUnicaNueva->id);

        // Segundo cambio intenta tomar la misma serie -> Debe fallar
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya no está disponible');

        $this->devolucionService->completar($dev2, $this->vendedor, $serieUnicaNueva->id);
    }

    public function test_devolucion_parcial_mantiene_la_venta_completada_y_calcula_saldo_devolvible(): void
    {
        // Ingresar stock inicial de mouses (sin series)
        MovimientoInventario::create([
            'producto_id' => $this->productoMouse->id,
            'serie_id' => null,
            'tipo' => TipoMovimiento::INGRESO,
            'cantidad' => 10,
            'stock_resultante' => 10,
            'referencia_tipo' => 'inventario_inicial',
            'referencia_id' => 1,
            'motivo' => 'Stock inicial de prueba',
            'user_id' => $this->admin->id,
        ]);

        $venta = $this->ventaService->registrar(
            datos: ['cliente_id' => $this->clienteDni->id, 'tipo_comprobante' => TipoComprobanteVenta::BOLETA],
            items: [
                ['producto_id' => $this->productoMouse->id, 'cantidad' => 3, 'precio_unitario' => 100.00],
            ],
            pagos: [['metodo' => MetodoPago::EFECTIVO, 'monto' => 300.00]],
            vendedor: $this->vendedor
        );

        $detalle = $venta->detalles()->first();

        // Devolver 1 mouse
        $dev1 = $this->devolucionService->solicitar(
            venta: $venta,
            items: [
                ['venta_detalle_id' => $detalle->id, 'cantidad' => 1, 'estado_fisico' => EstadoFisico::NUEVO_SELLADO],
            ],
            tipo: TipoDevolucion::DEVOLUCION_DINERO,
            motivo: MotivoDevolucion::ARREPENTIMIENTO,
            detalleMotivo: 'Devuelve 1 de los 3 mouses',
            solicitante: $this->vendedor,
            metodoReembolso: 'efectivo'
        );

        $this->devolucionService->aprobar($dev1, $this->admin);
        $this->devolucionService->completar($dev1, $this->vendedor);

        // La venta sigue completada
        $this->assertEquals(EstadoVenta::COMPLETADA, $venta->fresh()->estado);

        // Saldo devolvible restante: 2 mouses (S/ 200)
        $saldo = $this->devolucionService->saldoDevolvible($detalle);
        $this->assertEquals(2, $saldo['cantidad_disponible']);
        $this->assertEquals(200.00, $saldo['monto_disponible']);
    }

    public function test_solo_admin_aprueba_vendedor_solo_solicita(): void
    {
        $serie = $this->crearSerie($this->productoLaptop, 'SN-ROLE-001');

        $venta = $this->ventaService->registrar(
            datos: ['cliente_id' => $this->clienteDni->id, 'tipo_comprobante' => TipoComprobanteVenta::BOLETA],
            items: [['producto_id' => $this->productoLaptop->id, 'serie_id' => $serie->id, 'cantidad' => 1]],
            pagos: [['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00]],
            vendedor: $this->vendedor
        );

        $detalle = $venta->detalles()->first();

        $devolucion = $this->devolucionService->solicitar(
            venta: $venta,
            items: [['venta_detalle_id' => $detalle->id, 'serie_id' => $serie->id, 'cantidad' => 1, 'estado_fisico' => EstadoFisico::NUEVO_SELLADO]],
            tipo: TipoDevolucion::DEVOLUCION_DINERO,
            motivo: MotivoDevolucion::ARREPENTIMIENTO,
            detalleMotivo: 'Solicitud vendedor',
            solicitante: $this->vendedor,
            metodoReembolso: 'efectivo'
        );

        // Vendedor intentando aprobar -> Debe fallar
        try {
            $this->devolucionService->aprobar($devolucion, $this->vendedor);
            $this->fail('Vendedor no debe poder aprobar devoluciones');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Solo un usuario administrador', $e->getMessage());
        }

        // Admin aprobando -> Exitoso
        $aprobada = $this->devolucionService->aprobar($devolucion, $this->admin);
        $this->assertEquals(EstadoDevolucion::APROBADA, $aprobada->estado);
        $this->assertEquals($this->admin->id, $aprobada->aprobado_por);
    }
}
