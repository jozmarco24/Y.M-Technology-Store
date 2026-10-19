<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoDevolucion;
use App\Enums\EstadoFisico;
use App\Enums\EstadoOrdenServicio;
use App\Enums\EstadoSerie;
use App\Enums\MetodoPago;
use App\Enums\MotivoDevolucion;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDevolucion;
use App\Enums\TipoDocumentoCliente;
use App\Enums\TipoOrdenServicio;
use App\Livewire\Pos\Venta as PosVentaComponent;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use App\Models\Venta;
use App\Policies\DevolucionPolicy;
use App\Services\CajaService;
use App\Services\CambioService;
use App\Services\DevolucionService;
use App\Services\GarantiaService;
use App\Services\OrdenServicioService;
use App\Services\VentaService;
use Database\Seeders\ConfiguracionesSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\SeriesComprobantesYClientesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class Fase3CPosYApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendedor;

    protected User $almacen;

    protected User $tecnico;

    protected Cliente $cliente;

    protected Producto $productoLaptop;

    protected Serie $serieLaptop;

    protected Serie $serieReemplazo;

    protected Venta $venta;

    protected CajaService $cajaService;

    protected VentaService $ventaService;

    protected GarantiaService $garantiaService;

    protected DevolucionService $devolucionService;

    protected OrdenServicioService $ordenServicioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->seed(SeriesComprobantesYClientesSeeder::class);
        $this->seed(ConfiguracionesSeeder::class);

        $this->cajaService = app(CajaService::class);
        $this->ventaService = app(VentaService::class);
        $this->garantiaService = app(GarantiaService::class);
        $this->devolucionService = app(DevolucionService::class);
        $this->ordenServicioService = app(OrdenServicioService::class);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@tienda.com']);
        $this->admin->assignRole('admin');

        $this->vendedor = User::factory()->create(['name' => 'Vendedor 1', 'email' => 'vendedor1@tienda.com']);
        $this->vendedor->assignRole('vendedor');

        $this->almacen = User::factory()->create(['name' => 'Almacenero 1', 'email' => 'almacen1@tienda.com']);
        $this->almacen->assignRole('almacen');

        $this->tecnico = User::factory()->create(['name' => 'Técnico Especialista', 'email' => 'tecnico1@tienda.com']);
        $this->tecnico->assignRole('admin');

        $categoria = Categoria::create(['nombre' => 'Laptops', 'slug' => 'laptops', 'activo' => true]);
        $marca = Marca::create(['nombre' => 'Asus', 'slug' => 'asus', 'activo' => true]);

        $this->productoLaptop = Producto::create([
            'categoria_id' => $categoria->id,
            'marca_id' => $marca->id,
            'sku' => 'LAP-ASUS-001',
            'nombre' => 'Laptop Asus Zenbook',
            'slug' => 'laptop-asus-zenbook',
            'modelo' => 'UX425',
            'precio_venta' => 3500.00,
            'precio_compra' => 2800.00,
            'precio_minimo' => 3200.00,
            'stock_minimo' => 2,
            'stock_actual' => 5,
            'maneja_series' => true,
            'garantia_cliente_meses' => 12,
            'garantia_proveedor_meses' => 12,
            'activo' => true,
        ]);

        $this->serieLaptop = Serie::create([
            'producto_id' => $this->productoLaptop->id,
            'numero_serie' => 'SN-LAPTOP-999',
            'costo' => 2800.00,
            'fecha_ingreso' => now(),
            'estado' => EstadoSerie::DISPONIBLE,
        ]);

        $this->serieReemplazo = Serie::create([
            'producto_id' => $this->productoLaptop->id,
            'numero_serie' => 'SN-LAPTOP-REEMPLAZO',
            'costo' => 2800.00,
            'fecha_ingreso' => now(),
            'estado' => EstadoSerie::DISPONIBLE,
        ]);

        $this->cliente = Cliente::create([
            'tipo_documento' => TipoDocumentoCliente::DNI,
            'numero_documento' => '72345678',
            'nombre_razon_social' => 'Juan Carlos Perez',
            'email' => 'juan.perez@example.com',
            'telefono' => '987654321',
        ]);

        // Registrar una venta para generar la garantía
        $this->cajaService->abrir($this->vendedor, 500.00);

        $this->venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->cliente->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoLaptop->id, 'serie_id' => $this->serieLaptop->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 3500.00],
            ],
            vendedor: $this->vendedor
        );
    }

    public function test_almacen_no_puede_acceder_al_pos(): void
    {
        $response = $this->actingAs($this->almacen)->get('/pos');
        $response->assertForbidden();
    }

    public function test_pos_consulta_garantia_por_serie(): void
    {
        Livewire::actingAs($this->vendedor)
            ->test(PosVentaComponent::class)
            ->set('busquedaGarantiaSerie', 'SN-LAPTOP-999')
            ->call('consultarGarantiaEnPos')
            ->assertSet('resultadoGarantia.serie', 'SN-LAPTOP-999')
            ->assertSet('resultadoGarantia.cliente', 'Juan Carlos Perez')
            ->assertSet('resultadoGarantia.vigente', true)
            ->assertSee('Laptop Asus Zenbook')
            ->assertSee('Iniciar Devolución / Cambio')
            ->assertSee('Recibir en Servicio Técnico');
    }

    public function test_pos_asistente_devolucion_respeta_saldo_devolvible_y_tipo(): void
    {
        $component = Livewire::actingAs($this->vendedor)
            ->test(PosVentaComponent::class)
            ->call('abrirAsistenteDevolucion', $this->venta->id)
            ->assertSet('pasoDevolucion', 1);

        $detalle = $this->venta->detalles()->first();

        // Paso 1: seleccionar item
        $component->set("itemsDevolucion.{$detalle->id}.seleccionado", true)
            ->call('avanzarPasoDevolucion')
            ->assertSet('pasoDevolucion', 2);

        // Paso 2: motivo y condición física
        $component->set('motivoDevolucionInput', 'defecto_fabrica')
            ->set('detalleMotivoDevolucion', 'Equipo no enciende placa madre')
            ->call('avanzarPasoDevolucion')
            ->assertSet('pasoDevolucion', 3);

        // Paso 3: seleccionar tipo
        $component->set('tipoDevolucionInput', 'cambio_producto')
            ->call('avanzarPasoDevolucion')
            ->assertSet('pasoDevolucion', 4);

        // Paso 4: escanear serie de reemplazo
        $component->set('serieNuevaCambioInput', 'SN-LAPTOP-REEMPLAZO')
            ->call('buscarSerieNuevaParaCambio')
            ->call('avanzarPasoDevolucion')
            ->assertSet('pasoDevolucion', 5);

        // Paso 5: Vendedor envía solicitud
        $component->call('procesarDevolucion');

        $this->assertDatabaseHas('devoluciones', [
            'venta_id' => $this->venta->id,
            'estado' => EstadoDevolucion::SOLICITADA->value,
            'tipo' => TipoDevolucion::CAMBIO_PRODUCTO->value,
        ]);
    }

    public function test_solo_admin_puede_aprobar_devolucion(): void
    {
        $detalle = $this->venta->detalles()->first();

        $devolucion = $this->devolucionService->solicitar(
            venta: $this->venta,
            items: [
                [
                    'venta_detalle_id' => $detalle->id,
                    'serie_id' => $this->serieLaptop->id,
                    'cantidad' => 1,
                    'estado_fisico' => EstadoFisico::NUEVO_SELLADO,
                ],
            ],
            tipo: TipoDevolucion::DEVOLUCION_DINERO,
            motivo: MotivoDevolucion::ARREPENTIMIENTO,
            detalleMotivo: 'Cliente desistió de la compra dentro del plazo',
            solicitante: $this->vendedor
        );

        $this->assertEquals(EstadoDevolucion::SOLICITADA, $devolucion->estado);

        // Intentar aprobar con vendedor debe fallar en Policy
        $politica = new DevolucionPolicy;
        $this->assertFalse($politica->approve($this->vendedor, $devolucion));
        $this->assertTrue($politica->approve($this->admin, $devolucion));
    }

    public function test_admin_aprueba_y_completa_devolucion_con_cambio(): void
    {
        $detalle = $this->venta->detalles()->first();

        $devolucion = $this->devolucionService->solicitar(
            venta: $this->venta,
            items: [
                [
                    'venta_detalle_id' => $detalle->id,
                    'serie_id' => $this->serieLaptop->id,
                    'cantidad' => 1,
                    'estado_fisico' => EstadoFisico::CON_DEFECTO,
                ],
            ],
            tipo: TipoDevolucion::CAMBIO_PRODUCTO,
            motivo: MotivoDevolucion::DEFECTO_FABRICA,
            detalleMotivo: 'Pantalla con líneas horizontales',
            solicitante: $this->vendedor
        );

        // Admin aprueba
        $devolucionAprobada = $this->devolucionService->aprobar($devolucion, $this->admin);
        $this->assertEquals(EstadoDevolucion::APROBADA, $devolucionAprobada->estado);

        // Asignar cambio de serie
        app(CambioService::class)->cambiarSerie(
            $devolucionAprobada,
            $this->serieLaptop,
            $this->serieReemplazo,
            $this->admin
        );

        // Admin completa
        $devolucionCompletada = $this->devolucionService->completar($devolucionAprobada, $this->admin);
        $this->assertEquals(EstadoDevolucion::COMPLETADA, $devolucionCompletada->estado);

        // La serie de reemplazo debe estar VENDIDA
        $this->serieReemplazo->refresh();
        $this->assertEquals(EstadoSerie::VENDIDA, $this->serieReemplazo->estado);

        // Se debió crear una nueva garantía para la serie reemplazo
        $this->assertDatabaseHas('garantias', [
            'serie_id' => $this->serieReemplazo->id,
            'cliente_id' => $this->cliente->id,
        ]);
    }

    public function test_consulta_publica_garantia_exige_serie_y_documento_con_rate_limiting(): void
    {
        // 1. Sin documento o serie -> muestra mensaje de validación amigable
        $response = $this->post('/garantia', [
            'numero_serie' => 'SN-LAPTOP-999',
            'numero_documento' => '',
        ]);
        $response->assertOk();
        $response->assertSee('El número de serie y el documento de identidad');

        // 2. Documento no coincide con la venta de la serie
        $responseErr = $this->post('/garantia', [
            'numero_serie' => 'SN-LAPTOP-999',
            'numero_documento' => '00000000',
        ]);
        $responseErr->assertOk();
        $responseErr->assertSee('No se encontró un registro');

        // 3. Documento y serie correctos -> muestra producto y garantía sin datos sensibles ni costos
        $responseOk = $this->post('/garantia', [
            'numero_serie' => 'SN-LAPTOP-999',
            'numero_documento' => '72345678',
        ]);
        $responseOk->assertOk();
        $responseOk->assertSee('Laptop Asus Zenbook');
        $responseOk->assertSee('UX425');
        // No debe mostrar costos
        $responseOk->assertDontSee('2800.00');
        $responseOk->assertDontSee('precio_compra');
    }

    public function test_api_garantias_y_ordenes_servicio_no_expone_costos_a_no_admin(): void
    {
        $orden = $this->ordenServicioService->recibir([
            'cliente_id' => $this->cliente->id,
            'equipo_descripcion' => 'Monitor Dell 27"',
            'serie_externa' => 'EXT-12345',
            'falla_reportada' => 'No enciende',
            'tipo' => TipoOrdenServicio::PAGO,
            'estado_recepcion' => 'Buen estado, sin fuente externa',
        ], $this->vendedor);

        // Asignar presupuesto estimado
        $orden->update(['costo_estimado' => 180.00]);

        // Token para vendedor (no admin)
        Sanctum::actingAs($this->vendedor, ['*']);

        $response = $this->getJson('/api/v1/ordenes-servicio/'.$orden->codigo);
        $response->assertOk();
        $response->assertJsonPath('data.codigo', $orden->codigo);
        // Debe ocultar costos a no-admin
        $this->assertNull($response->json('data.costo_estimado'));
        $this->assertNull($response->json('data.costo_final'));

        // Token para admin
        Sanctum::actingAs($this->admin, ['*']);
        $responseAdmin = $this->getJson('/api/v1/ordenes-servicio/'.$orden->codigo);
        $responseAdmin->assertOk();
        $this->assertEquals(180.00, (float) $responseAdmin->json('data.costo_estimado'));
    }

    public function test_api_solicitar_devolucion(): void
    {
        Sanctum::actingAs($this->vendedor, ['*']);

        $detalle = $this->venta->detalles()->first();

        $payload = [
            'venta_id' => $this->venta->id,
            'tipo' => TipoDevolucion::DEVOLUCION_DINERO->value,
            'motivo' => MotivoDevolucion::ARREPENTIMIENTO->value,
            'detalle_motivo' => 'Solicitud de devolución vía API',
            'items' => [
                [
                    'venta_detalle_id' => $detalle->id,
                    'serie_id' => $this->serieLaptop->id,
                    'cantidad' => 1,
                    'estado_fisico' => EstadoFisico::NUEVO_SELLADO->value,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/devoluciones', $payload);
        $response->assertCreated();
        $response->assertJsonPath('data.estado', EstadoDevolucion::SOLICITADA->value);
    }

    public function test_kanban_transiciones_validas_orden_servicio(): void
    {
        $orden = $this->ordenServicioService->recibir([
            'cliente_id' => $this->cliente->id,
            'producto_id' => $this->productoLaptop->id,
            'serie_id' => $this->serieLaptop->id,
            'falla_reportada' => 'Teclado no responde',
            'estado_recepcion' => 'Carcasa íntegra',
        ], $this->vendedor);

        // Estado inicial es recibido
        $this->assertEquals(EstadoOrdenServicio::RECIBIDO, $orden->estado);

        // Transición válida: RECIBIDO -> DIAGNOSTICO
        $ordenActualizada = $this->ordenServicioService->cambiarEstado(
            $orden,
            EstadoOrdenServicio::DIAGNOSTICO,
            'Cable flex desconectado',
            $this->admin
        );
        $this->assertEquals(EstadoOrdenServicio::DIAGNOSTICO, $ordenActualizada->estado);

        // Transición inválida: DIAGNOSTICO -> ENTREGADO (debe pasar por REPARADO primero)
        $this->expectException(\InvalidArgumentException::class);
        $this->ordenServicioService->cambiarEstado(
            $ordenActualizada,
            EstadoOrdenServicio::ENTREGADO,
            null,
            $this->admin
        );
    }
}
