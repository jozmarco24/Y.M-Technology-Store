<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoCaja;
use App\Enums\EstadoGarantia;
use App\Enums\EstadoOrdenServicio;
use App\Enums\EstadoSerie;
use App\Enums\MetodoPago;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDocumentoCliente;
use App\Enums\TipoMovimiento;
use App\Enums\TipoOrdenServicio;
use App\Jobs\NotificarOrdenReparadaEmailJob;
use App\Models\CajaSesion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Garantia;
use App\Models\Marca;
use App\Models\MovimientoInventario;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioEvento;
use App\Models\OrdenServicioRepuesto;
use App\Models\PoliticaGarantia;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\SerieComprobante;
use App\Models\User;
use App\Services\GarantiaService;
use App\Services\InventarioService;
use App\Services\OrdenServicioService;
use App\Services\ReporteServicioService;
use App\Services\VentaService;
use Carbon\Carbon;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ServicioTecnicoTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendedor;

    protected User $almacen;

    protected User $tecnico;

    protected Cliente $cliente;

    protected Categoria $categoria;

    protected Marca $marca;

    protected Producto $productoLaptop;

    protected Producto $productoRepuesto;

    protected OrdenServicioService $servicio;

    protected GarantiaService $garantiaService;

    protected InventarioService $inventarioService;

    protected VentaService $ventaService;

    protected ReporteServicioService $reporteService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin Test', 'email' => 'admin@test.com']);
        $this->admin->assignRole('admin');

        $this->vendedor = User::factory()->create(['name' => 'Vendedor Test', 'email' => 'vendedor@test.com']);
        $this->vendedor->assignRole('vendedor');

        $this->almacen = User::factory()->create(['name' => 'Almacen Test', 'email' => 'almacen@test.com']);
        $this->almacen->assignRole('almacen');

        $this->tecnico = User::factory()->create(['name' => 'Tecnico Test', 'email' => 'tecnico@test.com']);
        $this->tecnico->assignRole('tecnico');

        // Series de comprobantes
        SerieComprobante::create(['tipo_comprobante' => 'boleta', 'serie' => 'B001', 'correlativo_actual' => 0, 'activo' => true]);
        SerieComprobante::create(['tipo_comprobante' => 'factura', 'serie' => 'F001', 'correlativo_actual' => 0, 'activo' => true]);
        SerieComprobante::create(['tipo_comprobante' => 'nota_venta', 'serie' => 'NV01', 'correlativo_actual' => 0, 'activo' => true]);

        // Política general
        PoliticaGarantia::create([
            'nombre' => 'Política General 12 meses',
            'categoria_id' => null,
            'producto_id' => null,
            'meses_garantia' => 12,
            'dias_cambio' => 7,
            'cubre' => 'Fallas de fábrica de componentes',
            'no_cubre' => 'Golpes o derrame de líquidos',
            'activo' => true,
        ]);

        $this->categoria = Categoria::create(['nombre' => 'Laptops', 'slug' => 'laptops', 'activo' => true]);
        $this->marca = Marca::create(['nombre' => 'Lenovo', 'slug' => 'lenovo', 'activo' => true]);

        $this->productoLaptop = Producto::create([
            'nombre' => 'ThinkPad T14',
            'sku' => 'TP-T14-001',
            'slug' => 'thinkpad-t14',
            'categoria_id' => $this->categoria->id,
            'marca_id' => $this->marca->id,
            'precio_venta' => 4500.00,
            'costo' => 3500.00,
            'controla_series' => true,
            'stock_minimo' => 2,
            'activo' => true,
        ]);

        $this->productoRepuesto = Producto::create([
            'nombre' => 'Módulo RAM 16GB DDR4',
            'sku' => 'RAM-16-DDR4',
            'slug' => 'modulo-ram-16gb-ddr4',
            'categoria_id' => $this->categoria->id,
            'marca_id' => $this->marca->id,
            'precio_venta' => 250.00,
            'costo' => 150.00,
            'controla_series' => false,
            'stock_minimo' => 5,
            'activo' => true,
        ]);

        // Stock de repuestos
        MovimientoInventario::create([
            'producto_id' => $this->productoRepuesto->id,
            'tipo' => TipoMovimiento::INGRESO,
            'cantidad' => 20,
            'stock_resultante' => 20,
            'motivo' => 'Stock inicial de repuestos',
            'user_id' => $this->admin->id,
        ]);

        $this->cliente = Cliente::create([
            'tipo_documento' => TipoDocumentoCliente::DNI,
            'numero_documento' => '47852369',
            'nombre_razon_social' => 'Juan Pérez Gómez',
            'email' => 'juan.perez@example.com',
            'telefono' => '999888777',
            'activo' => true,
        ]);

        $this->servicio = app(OrdenServicioService::class);
        $this->garantiaService = app(GarantiaService::class);
        $this->inventarioService = app(InventarioService::class);
        $this->ventaService = app(VentaService::class);
        $this->reporteService = app(ReporteServicioService::class);
    }

    protected function abrirCajaPara(User $usuario, float $monto = 500.00): CajaSesion
    {
        return CajaSesion::create([
            'user_id' => $usuario->id,
            'monto_apertura' => $monto,
            'estado' => EstadoCaja::ABIERTA,
            'abierta_en' => Carbon::now(),
        ]);
    }

    protected function crearSerieVendida(string $numero = 'SN-SRV-001'): Serie
    {
        return Serie::create([
            'producto_id' => $this->productoLaptop->id,
            'numero_serie' => $numero,
            'estado' => EstadoSerie::VENDIDA,
            'costo' => 3500.00,
            'fecha_ingreso' => Carbon::now()->subMonths(3),
        ]);
    }

    public function test_recibir_con_garantia_vigente_asigna_tipo_garantia_y_vencida_asigna_pago(): void
    {
        // 1. Serie con garantía vigente
        $serieVigente = $this->crearSerieVendida('SN-VIG-001');
        $garantiaVigente = Garantia::create([
            'serie_id' => $serieVigente->id,
            'venta_detalle_id' => null,
            'cliente_id' => $this->cliente->id,
            'fecha_inicio' => Carbon::today()->subMonths(2),
            'fecha_fin' => Carbon::today()->addMonths(10),
            'dias_cambio_hasta' => Carbon::today()->subMonths(1),
            'estado' => EstadoGarantia::VIGENTE,
            'condiciones_snapshot' => 'Garantía 12 meses',
        ]);

        $ordenGarantia = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'serie_id' => $serieVigente->id,
            'falla_reportada' => 'No enciende la pantalla',
            'estado_recepcion' => 'Carcasa en buen estado, incluye cargador original',
        ], $this->vendedor);

        $this->assertEquals(TipoOrdenServicio::GARANTIA, $ordenGarantia->tipo);
        $this->assertEquals($garantiaVigente->id, $ordenGarantia->garantia_id);
        $this->assertEquals(EstadoSerie::EN_GARANTIA, $serieVigente->fresh()->estado);

        // 2. Serie con garantía vencida
        $serieVencida = $this->crearSerieVendida('SN-VENC-002');
        Garantia::create([
            'serie_id' => $serieVencida->id,
            'venta_detalle_id' => null,
            'cliente_id' => $this->cliente->id,
            'fecha_inicio' => Carbon::today()->subMonths(14),
            'fecha_fin' => Carbon::today()->subMonths(2),
            'dias_cambio_hasta' => Carbon::today()->subMonths(13),
            'estado' => EstadoGarantia::VENCIDA,
            'condiciones_snapshot' => 'Garantía 12 meses',
        ]);

        $ordenPago = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'serie_id' => $serieVencida->id,
            'falla_reportada' => 'Teclado no responde',
            'estado_recepcion' => 'Desgaste de uso',
        ], $this->vendedor);

        $this->assertEquals(TipoOrdenServicio::PAGO, $ordenPago->tipo);
        // Al ser de pago, no se envía la serie a en_garantia
        $this->assertEquals(EstadoSerie::VENDIDA, $serieVencida->fresh()->estado);
    }

    public function test_serie_pasa_a_en_garantia_al_recibir_y_vuelve_a_vendida_al_entregar(): void
    {
        $serie = $this->crearSerieVendida('SN-CICLO-001');
        Garantia::create([
            'serie_id' => $serie->id,
            'venta_detalle_id' => null,
            'cliente_id' => $this->cliente->id,
            'fecha_inicio' => Carbon::today()->subMonths(1),
            'fecha_fin' => Carbon::today()->addMonths(11),
            'dias_cambio_hasta' => Carbon::today()->addDays(2),
            'estado' => EstadoGarantia::VIGENTE,
            'condiciones_snapshot' => 'Garantía vigente',
        ]);

        $orden = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'serie_id' => $serie->id,
            'falla_reportada' => 'Falla de BIOS',
            'estado_recepcion' => 'Sin golpes',
        ], $this->vendedor);

        $this->assertEquals(EstadoSerie::EN_GARANTIA, $serie->fresh()->estado);

        // Técnico diagnostica y repara
        $this->servicio->registrarDiagnostico($orden, 'Actualización de firmware necesaria', $this->tecnico);
        $this->servicio->marcarReparado($orden, 'Flasheo de BIOS exitoso', $this->tecnico);

        // Vendedor entrega al cliente
        $this->servicio->entregar($orden, 'Juan Pérez Gómez', $this->vendedor);

        $this->assertEquals(EstadoOrdenServicio::ENTREGADO, $orden->fresh()->estado);
        $this->assertEquals(EstadoSerie::VENDIDA, $serie->fresh()->estado);
    }

    public function test_transiciones_invalidas_son_rechazadas(): void
    {
        $orden = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'equipo_descripcion' => 'PC Gamer Custom',
            'falla_reportada' => 'Pantalla azul',
            'estado_recepcion' => 'Gabinete con rayones',
        ], $this->vendedor);

        $this->assertEquals(EstadoOrdenServicio::RECIBIDO, $orden->estado);

        // De RECIBIDO NO se puede saltar directamente a ENTREGADO
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Transición no permitida: no se puede pasar una orden de 'Recibido' a 'Entregado'.");

        $this->servicio->cambiarEstado($orden, EstadoOrdenServicio::ENTREGADO, 'Intento de entrega sin reparar', $this->admin);
    }

    public function test_cotizacion_requiere_aprobacion_del_cliente_antes_de_reparar_en_tipo_pago(): void
    {
        $orden = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'equipo_descripcion' => 'Impresora Epson L3150',
            'falla_reportada' => 'Atasco de papel constante',
            'estado_recepcion' => 'Con tinta',
        ], $this->vendedor);

        $this->assertEquals(TipoOrdenServicio::PAGO, $orden->tipo);

        // Diagnóstico y cotización
        $this->servicio->registrarDiagnostico($orden, 'Rodillo de alimentación desgastado', $this->tecnico);
        $this->servicio->cotizar($orden, 180.00, $this->tecnico);

        $orden->refresh();
        $this->assertEquals(EstadoOrdenServicio::ESPERANDO_APROBACION, $orden->estado);
        $this->assertEquals('180.00', (string) $orden->costo_estimado);

        // Si el cliente aprueba -> pasa a EN_REPARACION
        $this->servicio->registrarAprobacionCliente($orden, true, $this->vendedor);
        $this->assertEquals(EstadoOrdenServicio::EN_REPARACION, $orden->fresh()->estado);
        $this->assertTrue($orden->fresh()->aprobado_por_cliente);
    }

    public function test_cotizacion_rechazada_pasa_a_cancelado(): void
    {
        $orden = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'equipo_descripcion' => 'Laptop HP 15',
            'falla_reportada' => 'Pantalla rota',
            'estado_recepcion' => 'Golpe en esquina',
        ], $this->vendedor);

        $this->servicio->registrarDiagnostico($orden, 'Panel IPS rajado', $this->tecnico);
        $this->servicio->cotizar($orden, 650.00, $this->tecnico);

        // Cliente rechaza
        $this->servicio->registrarAprobacionCliente($orden, false, $this->vendedor);

        $this->assertEquals(EstadoOrdenServicio::CANCELADO, $orden->fresh()->estado);
        $this->assertFalse($orden->fresh()->aprobado_por_cliente);
    }

    public function test_repuesto_del_inventario_descuenta_stock_con_movimiento(): void
    {
        $orden = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'equipo_descripcion' => 'Desktop Dell OptiPlex',
            'falla_reportada' => 'Lentitud general',
            'estado_recepcion' => 'Buen estado',
        ], $this->vendedor);

        $stockAntes = $this->inventarioService->stockDisponible($this->productoRepuesto);

        $repuesto = $this->servicio->agregarRepuesto($orden, [
            'producto_id' => $this->productoRepuesto->id,
            'descripcion' => 'Upgrade RAM 16GB',
            'cantidad' => 2,
            'costo_unitario' => 150.00,
            'precio_unitario' => 250.00,
        ], $this->tecnico);

        $this->assertInstanceOf(OrdenServicioRepuesto::class, $repuesto);

        $stockDespues = $this->inventarioService->stockDisponible($this->productoRepuesto);
        $this->assertEquals($stockAntes - 2, $stockDespues);

        // Verifica movimiento de inventario tipo SALIDA
        $mov = MovimientoInventario::where('referencia_tipo', OrdenServicio::class)
            ->where('referencia_id', $orden->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(TipoMovimiento::SALIDA, $mov->tipo);
        $this->assertEquals(-2, $mov->cantidad);
    }

    public function test_garantia_precio_al_cliente_es_cero_y_costo_asumido_por_tienda(): void
    {
        $serie = $this->crearSerieVendida('SN-REP-GAR-001');
        Garantia::create([
            'serie_id' => $serie->id,
            'cliente_id' => $this->cliente->id,
            'fecha_inicio' => Carbon::today()->subMonths(1),
            'fecha_fin' => Carbon::today()->addMonths(11),
            'dias_cambio_hasta' => Carbon::today(),
            'estado' => EstadoGarantia::VIGENTE,
            'condiciones_snapshot' => 'Garantía vigente',
        ]);

        $ordenGarantia = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'serie_id' => $serie->id,
            'falla_reportada' => 'Falla módulo memoria interno',
            'estado_recepcion' => 'Sin alteraciones',
        ], $this->vendedor);

        $this->assertTrue($ordenGarantia->esGarantia());

        $repuesto = $this->servicio->agregarRepuesto($ordenGarantia, [
            'producto_id' => $this->productoRepuesto->id,
            'descripcion' => 'Reemplazo de módulo RAM por garantía',
            'cantidad' => 1,
            'costo_unitario' => 150.00,
            'precio_unitario' => 250.00, // Intentar cobrar
        ], $this->tecnico);

        // Regla: En garantía el precio cobrado al cliente es 0.00, costo 150.00
        $this->assertEquals('0.00', (string) $repuesto->precio_unitario);
        $this->assertEquals('150.00', (string) $repuesto->costo_unitario);
    }

    public function test_no_se_entrega_con_cobro_pendiente(): void
    {
        $orden = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'equipo_descripcion' => 'Monitor LG 24',
            'falla_reportada' => 'Parpadeo',
            'estado_recepcion' => 'Completo con cable',
        ], $this->vendedor);

        $this->servicio->registrarDiagnostico($orden, 'Condensadores quemados', $this->tecnico);
        $this->servicio->cotizar($orden, 120.00, $this->tecnico);
        $this->servicio->registrarAprobacionCliente($orden, true, $this->vendedor);
        $this->servicio->marcarReparado($orden, 'Cambio de condensadores', $this->tecnico, 120.00);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tiene un cobro pendiente de S/ 120');

        // Intento de entrega sin haber cobrado
        $this->servicio->entregar($orden, 'Juan Pérez Gómez', $this->vendedor);
    }

    public function test_no_se_cobra_dos_veces_y_requiere_caja_abierta(): void
    {
        $orden = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'equipo_descripcion' => 'Teclado Mecánico',
            'falla_reportada' => 'Tecla espacio no funciona',
            'estado_recepcion' => 'Sin caja',
        ], $this->vendedor);

        $this->servicio->cotizar($orden, 80.00, $this->tecnico);

        // 1. Cobrar sin caja abierta falla
        try {
            $this->servicio->cobrar($orden, [
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
                'pagos' => [['metodo' => MetodoPago::EFECTIVO, 'monto' => 80.00]],
            ], $this->vendedor);
            $this->fail('Debió fallar por no tener caja abierta.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no tiene una sesión de caja abierta', $e->getMessage());
        }

        // 2. Abrir caja y cobrar con éxito
        $this->abrirCajaPara($this->vendedor);

        $venta = $this->servicio->cobrar($orden, [
            'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            'pagos' => [['metodo' => MetodoPago::EFECTIVO, 'monto' => 80.00]],
        ], $this->vendedor);

        $this->assertNotNull($venta);
        $this->assertEquals($venta->id, $orden->fresh()->venta_id);

        // 3. Intento de cobrar por segunda vez es rechazado
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ya fue cobrada previamente');

        $this->servicio->cobrar($orden, [
            'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            'pagos' => [['metodo' => MetodoPago::EFECTIVO, 'monto' => 80.00]],
        ], $this->vendedor);
    }

    public function test_cada_cambio_de_estado_registra_un_evento_en_linea_de_tiempo(): void
    {
        $orden = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'equipo_descripcion' => 'Nintendo Switch',
            'falla_reportada' => 'Joy-Con drift',
            'estado_recepcion' => 'Consola y mandos',
        ], $this->vendedor);

        $this->servicio->registrarDiagnostico($orden, 'Palanca análoga izquierda desgastada', $this->tecnico);
        $this->servicio->cotizar($orden, 90.00, $this->tecnico);
        $this->servicio->registrarAprobacionCliente($orden, true, $this->vendedor);
        $this->servicio->marcarReparado($orden, 'Reemplazo de stick electromagnético', $this->tecnico, 90.00);

        $this->abrirCajaPara($this->vendedor);
        $this->servicio->cobrar($orden, [
            'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            'pagos' => [['metodo' => MetodoPago::EFECTIVO, 'monto' => 90.00]],
        ], $this->vendedor);

        $this->servicio->entregar($orden, 'Juan Pérez Gómez', $this->vendedor);

        // Contar eventos registrados
        $eventos = OrdenServicioEvento::where('orden_servicio_id', $orden->id)->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(6, $eventos->count());

        $estadosNuevos = $eventos->pluck('estado_nuevo')->map(fn ($e) => $e->value)->toArray();
        $this->assertContains(EstadoOrdenServicio::RECIBIDO->value, $estadosNuevos);
        $this->assertContains(EstadoOrdenServicio::DIAGNOSTICO->value, $estadosNuevos);
        $this->assertContains(EstadoOrdenServicio::ESPERANDO_APROBACION->value, $estadosNuevos);
        $this->assertContains(EstadoOrdenServicio::EN_REPARACION->value, $estadosNuevos);
        $this->assertContains(EstadoOrdenServicio::REPARADO->value, $estadosNuevos);
        $this->assertContains(EstadoOrdenServicio::ENTREGADO->value, $estadosNuevos);
    }

    public function test_notificacion_email_se_encola_al_marcar_reparado(): void
    {
        Queue::fake();

        $orden = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'equipo_descripcion' => 'Tablet Samsung',
            'falla_reportada' => 'No carga',
            'estado_recepcion' => 'Limpia',
        ], $this->vendedor);

        $this->servicio->registrarDiagnostico($orden, 'Pin de carga sulfatado', $this->tecnico);
        $this->servicio->cotizar($orden, 100.00, $this->tecnico);
        $this->servicio->registrarAprobacionCliente($orden, true, $this->vendedor);
        $this->servicio->marcarReparado($orden, 'Limpieza y resoldado de puerto USB-C', $this->tecnico, 100.00);

        Queue::assertPushed(NotificarOrdenReparadaEmailJob::class, function ($job) use ($orden) {
            return $job->ordenServicioId === $orden->id;
        });
    }

    public function test_sin_solucion_marca_estado_y_permite_entrega(): void
    {
        $orden = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'equipo_descripcion' => 'Placa Madre dañada por rayo',
            'falla_reportada' => 'Cortocircuito total',
            'estado_recepcion' => 'Huele a quemado',
        ], $this->vendedor);

        $this->servicio->registrarDiagnostico($orden, 'Pistas calcinadas irrecuperables', $this->tecnico);
        $this->servicio->sinSolucion($orden, 'Daño irreversible en circuito multicapa', $this->tecnico);

        $orden->refresh();
        $this->assertEquals(EstadoOrdenServicio::SIN_SOLUCION, $orden->estado);
        $this->assertEquals('0.00', (string) $orden->costo_final);

        // Sin solución se puede entregar al cliente sin costo
        $this->servicio->entregar($orden, 'Juan Pérez Gómez', $this->vendedor);
        $this->assertEquals(EstadoOrdenServicio::ENTREGADO, $orden->fresh()->estado);
    }

    public function test_reportes_de_servicio_tecnico(): void
    {
        $orden = $this->servicio->recibir([
            'cliente_id' => $this->cliente->id,
            'producto_id' => $this->productoLaptop->id,
            'falla_reportada' => 'Falla pantalla',
            'estado_recepcion' => 'OK',
            'fecha_promesa' => Carbon::yesterday()->toDateString(), // Atrasada a propósito
        ], $this->vendedor);

        // 1. Órdenes por estado
        $porEstado = $this->reporteService->ordenesPorEstado();
        $this->assertArrayHasKey('recibido', $porEstado);
        $this->assertGreaterThanOrEqual(1, $porEstado['recibido']);

        // 2. Órdenes atrasadas
        $atrasadas = $this->reporteService->ordenesAtrasadas();
        $this->assertTrue($atrasadas->contains('id', $orden->id));

        // 3. Fallas por producto
        $fallas = $this->reporteService->equiposConMayorNumeroDeFallas();
        $this->assertNotEmpty($fallas);

        // 4. Costo de garantías asumidas (solo admin)
        $costosGarantias = $this->reporteService->costoGarantiasAsumidasPorMes($this->admin);
        $this->assertNotNull($costosGarantias);

        // No-admin no puede ver costos de garantías
        $this->expectException(RuntimeException::class);
        $this->reporteService->costoGarantiasAsumidasPorMes($this->vendedor);
    }

    public function test_permisos_por_rol_en_servicio_tecnico(): void
    {
        // Técnico no puede recibir órdenes (solo ver y diagnosticar)
        $this->assertFalse($this->tecnico->can('servicio.recibir'));
        $this->assertTrue($this->tecnico->can('servicio.diagnosticar'));

        // Vendedor puede recibir y cobrar, pero no diagnosticar
        $this->assertTrue($this->vendedor->can('servicio.recibir'));
        $this->assertTrue($this->vendedor->can('servicio.cobrar'));
        $this->assertFalse($this->vendedor->can('servicio.diagnosticar'));

        // Almacén solo puede ver
        $this->assertTrue($this->almacen->can('servicio.ver'));
        $this->assertFalse($this->almacen->can('servicio.cobrar'));

        // Admin tiene todos los permisos
        $this->assertTrue($this->admin->can('servicio.forzar_tipo'));
        $this->assertTrue($this->admin->can('servicio.cobrar'));
    }
}
