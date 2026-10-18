<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoComprobante;
use App\Enums\EstadoSerie;
use App\Enums\MetodoPago;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDocumentoCliente;
use App\Jobs\EnviarComprobanteJob;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ComprobanteElectronico;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use App\Models\Venta;
use App\Services\CajaService;
use App\Services\Facturacion\FacturacionService;
use App\Services\Facturacion\NubefactEmisor;
use App\Services\VentaService;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\SeriesComprobantesYClientesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FacturacionElectronicaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendedor;

    protected Cliente $clienteDni;

    protected Cliente $clienteRuc;

    protected Producto $productoConSeries;

    protected Producto $productoSinSeries;

    protected VentaService $ventaService;

    protected CajaService $cajaService;

    protected FacturacionService $facturacionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->seed(SeriesComprobantesYClientesSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin Test', 'email' => 'admin@test.com']);
        $this->admin->assignRole('admin');

        $this->vendedor = User::factory()->create(['name' => 'Vendedor Test', 'email' => 'vendedor@test.com']);
        $this->vendedor->assignRole('vendedor');

        $this->clienteDni = Cliente::create([
            'tipo_documento' => TipoDocumentoCliente::DNI,
            'numero_documento' => '72345678',
            'nombre_razon_social' => 'CARLOS PRADA',
            'email' => 'carlos@cliente.test',
        ]);

        $this->clienteRuc = Cliente::create([
            'tipo_documento' => TipoDocumentoCliente::RUC,
            'numero_documento' => '20608889991',
            'nombre_razon_social' => 'EMPRESA TECNOLOGICA S.A.C.',
            'direccion' => 'Av. Conquistadores 123',
            'email' => 'facturacion@empresa.test',
        ]);

        $cat = Categoria::create(['nombre' => 'Laptops', 'slug' => 'laptops']);
        $marca = Marca::create(['nombre' => 'Lenovo', 'slug' => 'lenovo']);

        $this->productoConSeries = Producto::create([
            'categoria_id' => $cat->id,
            'marca_id' => $marca->id,
            'nombre' => 'ThinkPad T14 Gen 4',
            'slug' => 'thinkpad-t14-gen-4',
            'sku' => 'TP-T14-001',
            'controla_series' => true,
            'precio_venta' => 4500.00,
            'precio_minimo' => 4200.00,
            'precio_compra_referencial' => 3500.00,
            'stock_minimo' => 2,
            'activo' => true,
        ]);

        $this->productoSinSeries = Producto::create([
            'categoria_id' => $cat->id,
            'marca_id' => $marca->id,
            'nombre' => 'Mouse USB Lenovo',
            'slug' => 'mouse-usb-lenovo',
            'sku' => 'MS-LEN-001',
            'controla_series' => false,
            'precio_venta' => 50.00,
            'precio_minimo' => 40.00,
            'precio_compra_referencial' => 25.00,
            'stock_minimo' => 5,
            'activo' => true,
        ]);

        $this->ventaService = app(VentaService::class);
        $this->cajaService = app(CajaService::class);
        $this->facturacionService = app(FacturacionService::class);

        // Abrir caja para el vendedor
        $this->cajaService->abrir($this->vendedor, 200.00);
    }

    private function crearSerie(string $numero = 'SN-TEST-001'): Serie
    {
        return Serie::create([
            'producto_id' => $this->productoConSeries->id,
            'numero_serie' => $numero,
            'costo' => 3500.00,
            'estado' => EstadoSerie::DISPONIBLE,
            'fecha_ingreso' => now(),
        ]);
    }

    public function test_venta_de_boleta_crea_comprobante_pendiente_y_despacha_job(): void
    {
        Queue::fake();

        $serie = $this->crearSerie('SN-BOL-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4500.00],
            ],
            vendedor: $this->vendedor
        );

        $this->assertNotNull($venta->comprobanteElectronico);
        $this->assertEquals(EstadoComprobante::PENDIENTE, $venta->comprobanteElectronico->estado);
        $this->assertEquals('B001', $venta->comprobanteElectronico->serie);

        Queue::assertPushed(EnviarComprobanteJob::class, function ($job) use ($venta) {
            return $job->ventaId === $venta->id;
        });
    }

    public function test_nota_de_venta_no_genera_comprobante_electronico(): void
    {
        Queue::fake();

        $serie = $this->crearSerie('SN-NV-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::NOTA_VENTA,
            ],
            items: [
                ['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4500.00],
            ],
            vendedor: $this->vendedor
        );

        $this->assertNull($venta->comprobanteElectronico);
        $this->assertDatabaseMissing('comprobantes_electronicos', ['venta_id' => $venta->id]);
        Queue::assertNotPushed(EnviarComprobanteJob::class);
    }

    public function test_job_exitoso_con_emisor_simulado_actualiza_a_aceptado_guarda_enlaces_qr_y_hash(): void
    {
        $serie = $this->crearSerie('SN-SIM-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4500.00],
            ],
            vendedor: $this->vendedor
        );

        $job = new EnviarComprobanteJob($venta->id);
        $job->handle($this->facturacionService);

        $comprobante = $venta->fresh()->comprobanteElectronico;

        $this->assertEquals(EstadoComprobante::ACEPTADO, $comprobante->estado);
        $this->assertNotNull($comprobante->enlace_pdf);
        $this->assertNotNull($comprobante->enlace_xml);
        $this->assertNotNull($comprobante->enlace_cdr);
        $this->assertNotNull($comprobante->codigo_qr);
        $this->assertNotNull($comprobante->hash);
        $this->assertEquals(1, $comprobante->intentos);
        $this->assertNotNull($comprobante->aceptado_en);
    }

    public function test_idempotencia_no_se_reenvia_comprobante_ya_aceptado(): void
    {
        $serie = $this->crearSerie('SN-IDEMP-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4500.00],
            ],
            vendedor: $this->vendedor
        );

        $job = new EnviarComprobanteJob($venta->id);
        $job->handle($this->facturacionService);

        $intentosOriginal = $venta->fresh()->comprobanteElectronico->intentos;

        // Segundo intento no debe modificar los intentos ni reenviar
        $job->handle($this->facturacionService);

        $this->assertEquals($intentosOriginal, $venta->fresh()->comprobanteElectronico->intentos);
    }

    public function test_error_recuperable_5xx_o_timeout_en_nubefact_deja_comprobante_en_error(): void
    {
        config(['facturacion.modo' => 'produccion']);
        config(['facturacion.ruta' => 'https://api.nubefact.com/api/v1/test']);
        config(['facturacion.token' => 'token_secreto_test']);

        Http::fake([
            'https://api.nubefact.com/api/v1/test' => Http::response(['errors' => 'Error interno de SUNAT'], 503),
        ]);

        $serie = $this->crearSerie('SN-503-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4500.00],
            ],
            vendedor: $this->vendedor
        );

        $emisorReal = new NubefactEmisor;
        $service = new FacturacionService($emisorReal);

        $job = new EnviarComprobanteJob($venta->id);
        $job->handle($service);

        $comprobante = $venta->fresh()->comprobanteElectronico;

        $this->assertEquals(EstadoComprobante::ERROR, $comprobante->estado);
        $this->assertFalse($comprobante->estaAceptado());
        $this->assertTrue($comprobante->esReintentable());
    }

    public function test_error_definitivo_4xx_o_ruc_invalido_deja_comprobante_en_rechazado(): void
    {
        config(['facturacion.modo' => 'produccion']);
        config(['facturacion.ruta' => 'https://api.nubefact.com/api/v1/test']);
        config(['facturacion.token' => 'token_secreto_test']);

        Http::fake([
            'https://api.nubefact.com/api/v1/test' => Http::response([
                'errors' => 'El RUC no existe en el padrón de contribuyentes de SUNAT',
                'codigo' => '2001',
            ], 422),
        ]);

        $serie = $this->crearSerie('SN-422-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteRuc->id,
                'tipo_comprobante' => TipoComprobanteVenta::FACTURA,
            ],
            items: [
                ['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::TRANSFERENCIA, 'monto' => 4500.00],
            ],
            vendedor: $this->vendedor
        );

        $emisorReal = new NubefactEmisor;
        $service = new FacturacionService($emisorReal);

        $job = new EnviarComprobanteJob($venta->id);
        $job->handle($service);

        $comprobante = $venta->fresh()->comprobanteElectronico;

        $this->assertEquals(EstadoComprobante::RECHAZADO, $comprobante->estado);
        $this->assertStringContainsString('RUC', (string) $comprobante->mensaje_sunat);
        $this->assertFalse($comprobante->esReintentable());
    }

    public function test_si_nubefact_esta_caido_la_venta_igual_se_registra_correctamente(): void
    {
        config(['facturacion.modo' => 'produccion']);
        config(['facturacion.ruta' => 'https://api.nubefact.com/api/v1/test']);
        config(['facturacion.token' => 'token_secreto_test']);

        // Nubefact no responde o timeout
        Http::fake([
            'https://api.nubefact.com/api/v1/test' => Http::response(null, 500),
        ]);

        $serie = $this->crearSerie('SN-DOWN-001');

        // La venta DEBE registrarse sin arrojar excepción
        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4500.00],
            ],
            vendedor: $this->vendedor
        );

        $this->assertDatabaseHas('ventas', ['id' => $venta->id]);
        $this->assertDatabaseHas('comprobantes_electronicos', [
            'venta_id' => $venta->id,
        ]);
        $comprobante = $venta->fresh()->comprobanteElectronico;
        $this->assertContains($comprobante->estado, [EstadoComprobante::PENDIENTE, EstadoComprobante::ERROR]);
        $this->assertEquals(EstadoSerie::VENDIDA, $serie->fresh()->estado);
    }

    public function test_payload_contiene_numero_de_serie_del_equipo_y_montos_correctos(): void
    {
        $serie = $this->crearSerie('SN-PAYLOAD-999');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteRuc->id,
                'tipo_comprobante' => TipoComprobanteVenta::FACTURA,
            ],
            items: [
                ['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::TRANSFERENCIA, 'monto' => 4500.00],
            ],
            vendedor: $this->vendedor
        );

        $payload = $this->facturacionService->construirPayload($venta);

        $this->assertEquals(1, $payload['tipo_de_comprobante']); // 1 = Factura
        $this->assertEquals('F001', $payload['serie']);
        $this->assertEquals('20608889991', $payload['cliente_numero_de_documento']);
        $this->assertEquals(6, $payload['cliente_tipo_de_documento']); // 6 = RUC
        $this->assertCount(1, $payload['items']);

        $item = $payload['items'][0];
        $this->assertEquals('NIU', $item['unidad_de_medida']);
        $this->assertStringContainsString('SN: SN-PAYLOAD-999', $item['descripcion']);
        $this->assertEquals((float) $venta->total, (float) $payload['total']);
        $this->assertEquals((float) $venta->igv, (float) $payload['total_igv']);
    }

    public function test_anular_venta_con_comprobante_aceptado_genera_baja_en_sunat(): void
    {
        $serie = $this->crearSerie('SN-ANULAR-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteRuc->id,
                'tipo_comprobante' => TipoComprobanteVenta::FACTURA,
            ],
            items: [
                ['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::TRANSFERENCIA, 'monto' => 4500.00],
            ],
            vendedor: $this->vendedor
        );

        // Emitir comprobante en modo simulado
        $this->facturacionService->emitir($venta);
        $this->assertTrue($venta->fresh()->comprobanteElectronico->estaAceptado());

        // Anular venta como admin
        $this->ventaService->anular($venta, 'Error en digitación de RUC', $this->admin);

        $comprobante = $venta->fresh()->comprobanteElectronico;
        $this->assertStringContainsStringIgnoringCase('anulado', (string) $comprobante->mensaje_sunat);
    }

    public function test_token_no_aparece_en_los_logs(): void
    {
        $tokenSecreto = 'SUPER_SECRET_TOKEN_NUBEFACT_XYZ_123';
        config(['facturacion.modo' => 'produccion']);
        config(['facturacion.ruta' => 'https://api.nubefact.com/api/v1/test']);
        config(['facturacion.token' => $tokenSecreto]);

        Http::fake([
            'https://api.nubefact.com/api/v1/test' => Http::response(['errors' => 'Falla de prueba'], 500),
        ]);

        $serie = $this->crearSerie('SN-LOGS-001');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4500.00],
            ],
            vendedor: $this->vendedor
        );

        $emisor = new NubefactEmisor;
        $resultado = $emisor->emitir($venta);

        // El payload registrado en BD no debe contener el token
        $this->assertArrayNotHasKey('token', $resultado->payload);
        $this->assertArrayNotHasKey('Authorization', $resultado->payload);
    }

    public function test_scheduler_reintenta_solo_los_elegibles(): void
    {
        Queue::fake();

        // 1. Comprobante pendiente con 1 intento (elegible)
        $serie1 = $this->crearSerie('SN-SCHED-001');
        $venta1 = $this->ventaService->registrar(
            datos: ['cliente_id' => $this->clienteDni->id, 'tipo_comprobante' => TipoComprobanteVenta::BOLETA],
            items: [['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie1->id, 'cantidad' => 1]],
            pagos: [['metodo' => MetodoPago::EFECTIVO, 'monto' => 4500.00]],
            vendedor: $this->vendedor
        );
        $venta1->comprobanteElectronico->update(['intentos' => 1, 'estado' => EstadoComprobante::ERROR]);

        // 2. Comprobante rechazado (NO elegible)
        $serie2 = $this->crearSerie('SN-SCHED-002');
        $venta2 = $this->ventaService->registrar(
            datos: ['cliente_id' => $this->clienteDni->id, 'tipo_comprobante' => TipoComprobanteVenta::BOLETA],
            items: [['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie2->id, 'cantidad' => 1]],
            pagos: [['metodo' => MetodoPago::EFECTIVO, 'monto' => 4500.00]],
            vendedor: $this->vendedor
        );
        $venta2->comprobanteElectronico->update(['estado' => EstadoComprobante::RECHAZADO]);

        // 3. Comprobante con 5 o más intentos (NO elegible)
        $serie3 = $this->crearSerie('SN-SCHED-003');
        $venta3 = $this->ventaService->registrar(
            datos: ['cliente_id' => $this->clienteDni->id, 'tipo_comprobante' => TipoComprobanteVenta::BOLETA],
            items: [['producto_id' => $this->productoConSeries->id, 'serie_id' => $serie3->id, 'cantidad' => 1]],
            pagos: [['metodo' => MetodoPago::EFECTIVO, 'monto' => 4500.00]],
            vendedor: $this->vendedor
        );
        $venta3->comprobanteElectronico->update(['intentos' => 5, 'estado' => EstadoComprobante::ERROR]);

        // Ejecutar query de elegibles
        $elegibles = ComprobanteElectronico::reintentables(5)->pluck('venta_id')->all();

        $this->assertContains($venta1->id, $elegibles);
        $this->assertNotContains($venta2->id, $elegibles);
        $this->assertNotContains($venta3->id, $elegibles);
    }
}
