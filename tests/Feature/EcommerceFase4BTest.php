<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Enums\EstadoSerie;
use App\Enums\MetodoEntrega;
use App\Enums\Pasarela;
use App\Enums\TipoCupon;
use App\Models\Banner;
use App\Models\Categoria;
use App\Models\ClienteWeb;
use App\Models\Cupon;
use App\Models\Marca;
use App\Models\Pagina;
use App\Models\PagoOnline;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use App\Models\ZonaEnvio;
use App\Services\CarritoService;
use App\Services\PagoOnlineService;
use App\Services\PedidoService;
use App\Services\ReclamacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcommerceFase4BTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Categoria $categoriaLaptops;

    protected Marca $marcaDell;

    protected Producto $laptop;

    protected Serie $serie1;

    protected Serie $serie2;

    protected ClienteWeb $clienteWeb;

    protected ZonaEnvio $zonaLima;

    protected Cupon $cupon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::role('admin')->first();

        $this->categoriaLaptops = Categoria::create([
            'nombre' => 'Laptops Portátiles',
            'slug' => 'laptops-portatiles',
            'activo' => true,
            'orden' => 1,
        ]);

        $this->marcaDell = Marca::create([
            'nombre' => 'Dell Technologies',
            'slug' => 'dell-technologies',
            'activo' => true,
        ]);

        $this->laptop = Producto::create([
            'sku' => 'DELL-XPS-15',
            'nombre' => 'Dell XPS 15 9530',
            'slug' => 'dell-xps-15-9530',
            'descripcion' => 'Potente laptop con Intel Core i7 y pantalla OLED',
            'categoria_id' => $this->categoriaLaptops->id,
            'marca_id' => $this->marcaDell->id,
            'modelo' => 'XPS 9530',
            'precio_compra_referencial' => 4500.00,
            'precio_venta' => 6000.00,
            'incluye_igv' => true,
            'controla_series' => true,
            'stock_minimo' => 1,
            'activo' => true,
            'publicado_web' => true,
            'destacado' => true,
            'especificaciones' => [
                'RAM' => '32GB DDR5',
                'Procesador' => 'Intel Core i7-13700H',
                'Pantalla' => '15.6 3.5K OLED',
            ],
        ]);

        $this->serie1 = Serie::create([
            'producto_id' => $this->laptop->id,
            'numero_serie' => 'SN-DELL-XPS-001',
            'estado' => EstadoSerie::DISPONIBLE,
            'fecha_ingreso' => now()->subDays(5),
            'costo' => 4500.00,
        ]);

        $this->serie2 = Serie::create([
            'producto_id' => $this->laptop->id,
            'numero_serie' => 'SN-DELL-XPS-002',
            'estado' => EstadoSerie::DISPONIBLE,
            'fecha_ingreso' => now()->subDays(2),
            'costo' => 4500.00,
        ]);

        $this->clienteWeb = ClienteWeb::create([
            'nombre' => 'María Pérez',
            'email' => 'maria.perez@example.com',
            'telefono' => '987654321',
            'password' => Hash::make('secret1234'),
            'activo' => true,
        ]);

        $this->zonaLima = ZonaEnvio::create([
            'nombre' => 'Lima Centro',
            'departamento' => 'LIMA',
            'provincia' => 'LIMA',
            'distrito' => 'Cercado de Lima',
            'costo' => 10.00,
            'dias_estimados' => 1,
            'activo' => true,
        ]);

        $this->cupon = Cupon::create([
            'codigo' => 'DESC50',
            'tipo' => TipoCupon::MONTO,
            'valor' => 50.00,
            'minimo_compra' => 500.00,
            'activo' => true,
        ]);

        Pagina::create([
            'titulo' => 'Términos y Condiciones',
            'slug' => 'terminos-y-condiciones',
            'contenido' => 'Términos generales del servicio.',
            'publicada' => true,
        ]);

        Banner::create([
            'titulo' => 'Gran Venta Anual 2026',
            'subtitulo' => 'Descuentos exclusivos en hardware',
            'imagen_url' => 'banners/banner1.jpg',
            'enlace' => '/catalogo',
            'activo' => true,
        ]);
    }

    public function test_catalogo_y_filtros_no_exponen_costos_ni_series(): void
    {
        $response = $this->get('/catalogo');
        $response->assertStatus(200);
        $response->assertSee('Dell XPS 15 9530');
        $response->assertSee('6,000.00');
        // Nunca debe mostrar costo ni el número de serie en el HTML público
        $response->assertDontSee('4500.00');
        $response->assertDontSee('4,500.00');
        $response->assertDontSee('SN-DELL-XPS-001');
    }

    public function test_agregar_al_carrito_y_fusion_al_login(): void
    {
        $tokenInvitado = (string) Str::uuid();
        $carritoService = app(CarritoService::class);
        $carrito = $carritoService->obtenerOCrear(token: $tokenInvitado);
        $carritoService->agregarProducto($carrito, $this->laptop, 1);

        $this->assertCount(1, $carrito->items);

        // Cliente inicia sesión y fusiona
        $carritoFusionado = $carritoService->fusionarCarritoInvitado($tokenInvitado, $this->clienteWeb);
        $this->assertEquals($this->clienteWeb->id, $carritoFusionado->cliente_web_id);
        $this->assertCount(1, $carritoFusionado->items);
    }

    public function test_checkout_completo_con_pasarela_simulada_genera_pedido_pagado_y_venta(): void
    {
        $carritoService = app(CarritoService::class);
        $carrito = $carritoService->obtenerOCrear($this->clienteWeb);
        $carritoService->agregarProducto($carrito, $this->laptop, 1);

        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '77889900',
            'email_contacto' => 'maria.perez@example.com',
            'telefono_contacto' => '987654321',
        ]);

        $this->assertEquals(EstadoPedido::PENDIENTE_PAGO, $pedido->estado);
        $this->assertEquals(EstadoSerie::RESERVADA, $this->serie1->fresh()->estado);

        // Pago con PasarelaSimulada
        $pagoService = app(PagoOnlineService::class);
        $inicio = $pagoService->iniciar($pedido, Pasarela::SIMULADA);
        $pago = PagoOnline::find($inicio['pago_id']);
        $pedidoConfirmado = $pagoService->confirmar($pago, ['referencia' => 'SIM-OK-123']);

        $this->assertEquals(EstadoPedido::PAGADO, $pedidoConfirmado->estado);
        $this->assertNotNull($pedidoConfirmado->venta_id);
        $this->assertEquals(EstadoSerie::VENDIDA, $this->serie1->fresh()->estado);
    }

    public function test_factura_exige_ruc_en_checkout(): void
    {
        $carritoService = app(CarritoService::class);
        $carrito = $carritoService->obtenerOCrear($this->clienteWeb);
        $carritoService->agregarProducto($carrito, $this->laptop, 1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Para emitir Factura es obligatorio ingresar un RUC válido');

        app(PedidoService::class)->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'factura',
            'documento_tipo' => 'DNI',
            'documento_numero' => '44556677',
            'email_contacto' => 'maria.perez@example.com',
        ]);
    }

    public function test_pedido_expirado_libera_stock(): void
    {
        $carritoService = app(CarritoService::class);
        $carrito = $carritoService->obtenerOCrear($this->clienteWeb);
        $carritoService->agregarProducto($carrito, $this->laptop, 1);

        $pedido = app(PedidoService::class)->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '77889900',
            'email_contacto' => 'maria.perez@example.com',
        ]);

        $this->assertEquals(EstadoSerie::RESERVADA, $this->serie1->fresh()->estado);

        // Vencer reserva y ejecutar expiración
        $pedido->update(['reserva_expira_en' => now()->subMinutes(1)]);
        app(PedidoService::class)->expirarVencidos();

        $this->assertEquals(EstadoPedido::EXPIRADO, $pedido->fresh()->estado);
        $this->assertEquals(EstadoSerie::DISPONIBLE, $this->serie1->fresh()->estado);
    }

    public function test_reclamacion_genera_codigo_correlativo_y_se_guarda(): void
    {
        $service = app(ReclamacionService::class);
        $reclamacion = $service->registrar([
            'tipo' => 'reclamo',
            'consumidor_nombre' => 'Juan Reclamante',
            'consumidor_tipo_documento' => 'DNI',
            'consumidor_numero_documento' => '12345678',
            'consumidor_telefono' => '999888777',
            'consumidor_email' => 'juan@example.com',
            'consumidor_domicilio' => 'Av. Los Próceres 456, Surco',
            'bien_contratado' => 'producto',
            'detalle_reclamacion' => 'Equipo presentó inconvenientes con el cargador',
            'pedido_consumidor' => 'Cambio de cargador por garantía',
        ]);

        $this->assertStringStartsWith('REC-', $reclamacion->codigo);
        $this->assertEquals('pendiente', $reclamacion->estado);
        $this->assertDatabaseHas('reclamaciones', ['codigo' => $reclamacion->codigo]);
    }

    public function test_sitemap_y_robots_txt_se_generan_correctamente(): void
    {
        $responseSitemap = $this->get('/sitemap.xml');
        $responseSitemap->assertStatus(200);
        $responseSitemap->assertHeader('Content-Type', 'text/xml; charset=utf-8');
        $responseSitemap->assertSee('dell-xps-15-9530');

        $responseRobots = $this->get('/robots.txt');
        $responseRobots->assertStatus(200);
        $responseRobots->assertSee('User-agent: *');
        $responseRobots->assertSee('Disallow: /admin/');
        $responseRobots->assertSee('sitemap.xml');
    }

    public function test_cliente_no_ve_pedidos_ajenos_en_seguimiento_sin_autenticacion(): void
    {
        $carritoService = app(CarritoService::class);
        $carrito = $carritoService->obtenerOCrear($this->clienteWeb);
        $carritoService->agregarProducto($carrito, $this->laptop, 1);

        $pedido = app(PedidoService::class)->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '77889900',
            'email_contacto' => 'maria.perez@example.com',
        ]);

        // Sin estar logueado y sin ingresar el email del pedido, no se muestra el detalle
        $response = $this->get("/pedido/{$pedido->codigo}");
        $response->assertStatus(200);
        $response->assertSee('Acceso a Seguimiento de Invitado');
        $response->assertDontSee('Total Pagado');
    }
}
