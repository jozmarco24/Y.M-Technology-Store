<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Enums\EstadoSerie;
use App\Enums\MetodoEntrega;
use App\Enums\MetodoPago;
use App\Enums\Pasarela;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoCupon;
use App\Enums\TipoDocumentoCliente;
use App\Models\Carrito;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ClienteWeb;
use App\Models\Cupon;
use App\Models\Marca;
use App\Models\PagoOnline;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use App\Models\ZonaEnvio;
use App\Services\CajaService;
use App\Services\CarritoService;
use App\Services\CatalogoWebService;
use App\Services\CuponService;
use App\Services\EnvioService;
use App\Services\PagoOnlineService;
use App\Services\PedidoService;
use App\Services\VentaService;
use Carbon\Carbon;
use Database\Seeders\ConfiguracionesSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\SeriesComprobantesYClientesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class EcommerceFase4ATest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendedor;

    protected ClienteWeb $clienteWeb;

    protected Producto $laptop;

    protected Serie $serie1;

    protected Serie $serie2;

    protected ZonaEnvio $zonaLima;

    protected Cupon $cupon;

    protected CatalogoWebService $catalogoWebService;

    protected CarritoService $carritoService;

    protected EnvioService $envioService;

    protected CuponService $cuponService;

    protected PedidoService $pedidoService;

    protected PagoOnlineService $pagoOnlineService;

    protected VentaService $ventaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->seed(SeriesComprobantesYClientesSeeder::class);
        $this->seed(ConfiguracionesSeeder::class);

        $this->catalogoWebService = app(CatalogoWebService::class);
        $this->carritoService = app(CarritoService::class);
        $this->envioService = app(EnvioService::class);
        $this->cuponService = app(CuponService::class);
        $this->pedidoService = app(PedidoService::class);
        $this->pagoOnlineService = app(PagoOnlineService::class);
        $this->ventaService = app(VentaService::class);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@tienda.com']);
        $this->admin->assignRole('admin');

        $this->vendedor = User::factory()->create(['name' => 'Vendedor 1', 'email' => 'vendedor1@tienda.com']);
        $this->vendedor->assignRole('vendedor');

        $categoria = Categoria::create(['nombre' => 'Laptops', 'slug' => 'laptops', 'activo' => true]);
        $marca = Marca::create(['nombre' => 'Dell', 'slug' => 'dell', 'activo' => true]);

        $this->laptop = Producto::create([
            'categoria_id' => $categoria->id,
            'marca_id' => $marca->id,
            'sku' => 'LAP-DELL-001',
            'nombre' => 'Dell Inspiron 15',
            'slug' => 'dell-inspiron-15',
            'modelo' => '3520',
            'precio_venta' => 2500.00,
            'precio_compra' => 1900.00,
            'precio_minimo' => 2200.00,
            'stock_minimo' => 2,
            'stock_actual' => 2,
            'maneja_series' => true,
            'activo' => true,
            'publicado_web' => true,
            'destacado' => true,
        ]);

        $this->serie1 = Serie::create([
            'producto_id' => $this->laptop->id,
            'numero_serie' => 'SN-DELL-001',
            'costo' => 1900.00,
            'fecha_ingreso' => Carbon::now()->subDays(10),
            'estado' => EstadoSerie::DISPONIBLE,
        ]);

        $this->serie2 = Serie::create([
            'producto_id' => $this->laptop->id,
            'numero_serie' => 'SN-DELL-002',
            'costo' => 1900.00,
            'fecha_ingreso' => Carbon::now()->subDays(5),
            'estado' => EstadoSerie::DISPONIBLE,
        ]);

        $this->clienteWeb = ClienteWeb::create([
            'nombre' => 'Ana Gomez',
            'email' => 'ana.gomez@example.com',
            'telefono' => '987111222',
            'password' => bcrypt('secret123'),
            'activo' => true,
        ]);

        $this->zonaLima = ZonaEnvio::create([
            'nombre' => 'Lima Metropolitana',
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Miraflores',
            'costo' => 12.00,
            'dias_estimados' => 1,
            'activo' => true,
        ]);

        $this->cupon = Cupon::create([
            'codigo' => 'PROMO10',
            'tipo' => TipoCupon::PORCENTAJE,
            'valor' => 10.00,
            'minimo_compra' => 1000.00,
            'usos_maximos' => 5,
            'usos_actuales' => 0,
            'activo' => true,
        ]);
    }

    public function test_catalogo_web_nunca_expone_costos_y_lista_publicados(): void
    {
        $paginados = $this->catalogoWebService->listarPublicados();
        $this->assertCount(1, $paginados);

        $productoData = $paginados->first()->toArray();
        $this->assertArrayNotHasKey('costo', $productoData);
        $this->assertArrayNotHasKey('precio_compra', $productoData);

        $detalle = $this->catalogoWebService->obtenerPorSlug('dell-inspiron-15');
        $this->assertNotNull($detalle);
        $this->assertEquals(2, $detalle->stock_disponible);
    }

    public function test_crear_pedido_reserva_series_concretas_fifo_y_descuenta_stock(): void
    {
        $carrito = $this->carritoService->obtenerOCrear($this->clienteWeb);
        $this->carritoService->agregarProducto($carrito, $this->laptop, 1);

        $pedido = $this->pedidoService->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::DELIVERY_LIMA,
            'zona_envio_id' => $this->zonaLima->id,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '45678912',
            'email_contacto' => 'ana.gomez@example.com',
        ]);

        $this->assertEquals(EstadoPedido::PENDIENTE_PAGO, $pedido->estado);
        $this->assertNotNull($pedido->reserva_expira_en);

        // La serie más antigua (serie1) debe haber sido reservada por FIFO
        $this->serie1->refresh();
        $this->assertEquals(EstadoSerie::RESERVADA, $this->serie1->estado);

        // El stock disponible ahora debe ser 1
        $stockRestante = $this->laptop->seriesDisponibles()->count();
        $this->assertEquals(1, $stockRestante);

        // El carrito queda vacío
        $this->assertCount(0, $carrito->fresh()->items);
    }

    public function test_dos_clientes_comprando_la_ultima_unidad_a_la_vez_solo_uno_reserva(): void
    {
        // Dejar solo 1 serie disponible
        $this->serie2->update(['estado' => EstadoSerie::BAJA]);

        $token1 = (string) Str::uuid();
        $token2 = (string) Str::uuid();

        $carrito1 = $this->carritoService->obtenerOCrear(token: $token1);
        $this->carritoService->agregarProducto($carrito1, $this->laptop, 1);

        $carrito2 = $this->carritoService->obtenerOCrear(token: $token2);
        $this->carritoService->agregarProducto($carrito2, $this->laptop, 1);

        // Primer cliente reserva con éxito
        $pedido1 = $this->pedidoService->crearDesdeCarrito($carrito1, [
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '11223344',
            'email_contacto' => 'user1@example.com',
        ]);
        $this->assertEquals(EstadoPedido::PENDIENTE_PAGO, $pedido1->estado);

        // Segundo cliente intenta crear pedido sobre la misma unidad -> debe lanzar RuntimeException por falta de stock
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock insuficiente');

        $this->pedidoService->crearDesdeCarrito($carrito2, [
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '55667788',
            'email_contacto' => 'user2@example.com',
        ]);
    }

    public function test_precio_cambiado_entre_carrito_y_pago_se_revalida(): void
    {
        $carrito = $this->carritoService->obtenerOCrear($this->clienteWeb);
        $this->carritoService->agregarProducto($carrito, $this->laptop, 1);

        // Subir precio del producto antes de generar el pedido
        $this->laptop->update(['precio_venta' => 2800.00]);

        $totales = $this->carritoService->calcularTotalesYValidar($carrito);
        $this->assertEquals(2800.00, $totales['total']);

        $pedido = $this->pedidoService->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '45678912',
            'email_contacto' => 'ana.gomez@example.com',
        ]);

        $this->assertEquals(2800.00, (float) $pedido->subtotal);
    }

    public function test_reserva_expirada_libera_las_series(): void
    {
        $carrito = $this->carritoService->obtenerOCrear($this->clienteWeb);
        $this->carritoService->agregarProducto($carrito, $this->laptop, 1);

        $pedido = $this->pedidoService->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '45678912',
            'email_contacto' => 'ana.gomez@example.com',
        ]);

        $this->serie1->refresh();
        $this->assertEquals(EstadoSerie::RESERVADA, $this->serie1->estado);

        // Simular que el tiempo de reserva expiró
        $pedido->update(['reserva_expira_en' => Carbon::now()->subMinutes(1)]);

        $expirados = $this->pedidoService->expirarVencidos();
        $this->assertEquals(1, $expirados);

        $pedido->refresh();
        $this->assertEquals(EstadoPedido::EXPIRADO, $pedido->estado);

        // La serie debe haber vuelto a DISPONIBLE
        $this->serie1->refresh();
        $this->assertEquals(EstadoSerie::DISPONIBLE, $this->serie1->estado);
    }

    public function test_cancelar_pedido_libera_series(): void
    {
        $carrito = $this->carritoService->obtenerOCrear($this->clienteWeb);
        $this->carritoService->agregarProducto($carrito, $this->laptop, 1);

        $pedido = $this->pedidoService->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '45678912',
            'email_contacto' => 'ana.gomez@example.com',
        ]);

        $this->pedidoService->cancelar($pedido, 'Cliente cambió de opinión', $this->admin);

        $this->assertEquals(EstadoPedido::CANCELADO, $pedido->fresh()->estado);
        $this->serie1->refresh();
        $this->assertEquals(EstadoSerie::DISPONIBLE, $this->serie1->estado);
    }

    public function test_pago_confirmado_convierte_pedido_en_venta_series_vendida_y_garantia_creada(): void
    {
        $carrito = $this->carritoService->obtenerOCrear($this->clienteWeb);
        $this->carritoService->agregarProducto($carrito, $this->laptop, 1);

        $pedido = $this->pedidoService->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'codigo_cupon' => 'PROMO10',
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '45678912',
            'email_contacto' => 'ana.gomez@example.com',
        ]);

        $inicioPago = $this->pagoOnlineService->iniciar($pedido, Pasarela::SIMULADA);
        $pago = PagoOnline::find($inicioPago['pago_id']);

        $pedidoConfirmado = $this->pagoOnlineService->confirmar($pago, ['referencia' => 'TX-CONFIRMADA-99']);

        $this->assertEquals(EstadoPedido::PAGADO, $pedidoConfirmado->estado);
        $this->assertNotNull($pedidoConfirmado->venta_id);

        // Venta real creada con canal web
        $venta = $pedidoConfirmado->venta;
        $this->assertNotNull($venta);
        $this->assertEquals('web', $venta->canal);

        // La serie física debe estar VENDIDA
        $this->serie1->refresh();
        $this->assertEquals(EstadoSerie::VENDIDA, $this->serie1->estado);

        // Garantía creada
        $this->assertDatabaseHas('garantias', [
            'serie_id' => $this->serie1->id,
        ]);

        // Cupón sumó 1 uso
        $this->assertEquals(1, $this->cupon->fresh()->usos_actuales);
    }

    public function test_webhook_duplicado_no_procesa_dos_veces(): void
    {
        $carrito = $this->carritoService->obtenerOCrear($this->clienteWeb);
        $this->carritoService->agregarProducto($carrito, $this->laptop, 1);

        $pedido = $this->pedidoService->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '45678912',
            'email_contacto' => 'ana.gomez@example.com',
        ]);

        $inicioPago = $this->pagoOnlineService->iniciar($pedido, Pasarela::SIMULADA);
        $pago = PagoOnline::find($inicioPago['pago_id']);

        $payload = [
            'id' => 'evt_test_12345',
            'metadata' => ['idempotency_key' => $pago->idempotency_key],
        ];

        // Primer webhook
        $res1 = $this->pagoOnlineService->procesarWebhook('simulada', $payload);
        $this->assertTrue($res1['procesado']);
        $this->assertFalse($res1['duplicado']);

        // Segundo webhook con mismo evento_id
        $res2 = $this->pagoOnlineService->procesarWebhook('simulada', $payload);
        $this->assertTrue($res2['procesado']);
        $this->assertTrue($res2['duplicado']);
    }

    public function test_webhook_con_firma_invalida_es_rechazado(): void
    {
        $payload = ['id' => 'evt_fake'];
        $this->expectException(InvalidArgumentException::class);
        $this->pagoOnlineService->procesarWebhook('simulada', $payload, 'firma_invalida');
    }

    public function test_pago_tardio_con_series_ya_vendidas_marca_reembolso(): void
    {
        $carrito = $this->carritoService->obtenerOCrear($this->clienteWeb);
        $this->carritoService->agregarProducto($carrito, $this->laptop, 1);

        $pedido = $this->pedidoService->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '45678912',
            'email_contacto' => 'ana.gomez@example.com',
        ]);

        $inicioPago = $this->pagoOnlineService->iniciar($pedido, Pasarela::SIMULADA);
        $pago = PagoOnline::find($inicioPago['pago_id']);

        // Expirar reserva y vender la serie en la tienda física
        $pedido->update(['reserva_expira_en' => Carbon::now()->subMinutes(1)]);
        $this->pedidoService->expirarVencidos();
        $this->serie1->update(['estado' => EstadoSerie::VENDIDA]);

        // Llegada tardía del pago
        $pedidoFinal = $this->pagoOnlineService->confirmar($pago);

        $this->assertEquals(EstadoPedido::REEMBOLSADO, $pedidoFinal->estado);
        $this->assertStringContainsString('reembolso', strtolower($pedidoFinal->cancelado_motivo));
    }

    public function test_cupon_vigencia_minimo_y_usos_maximos(): void
    {
        // 1. Minimo compra no alcanzado
        $resMinimo = $this->cuponService->validar('PROMO10', 500.00);
        $this->assertFalse($resMinimo['valido']);

        // 2. Descuento correcto
        $resOk = $this->cuponService->validar('PROMO10', 2000.00);
        $this->assertTrue($resOk['valido']);
        $this->assertEquals(200.00, $resOk['descuento']);

        // 3. Usos máximos alcanzados
        $this->cupon->update(['usos_actuales' => 5]);
        $resLleno = $this->cuponService->validar('PROMO10', 2000.00);
        $this->assertFalse($resLleno['valido']);
    }

    public function test_factura_sin_ruc_es_rechazada(): void
    {
        $carrito = $this->carritoService->obtenerOCrear($this->clienteWeb);
        $this->carritoService->agregarProducto($carrito, $this->laptop, 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Para emitir Factura es obligatorio ingresar un RUC válido');

        $this->pedidoService->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'factura',
            'documento_tipo' => 'DNI',
            'documento_numero' => '45678912',
            'email_contacto' => 'ana.gomez@example.com',
        ]);
    }

    public function test_fusion_de_carrito_de_invitado_al_iniciar_sesion(): void
    {
        $tokenInvitado = (string) Str::uuid();
        $carritoInvitado = $this->carritoService->obtenerOCrear(token: $tokenInvitado);
        $this->carritoService->agregarProducto($carritoInvitado, $this->laptop, 1);

        // Cliente inicia sesión y fusiona
        $carritoCliente = $this->carritoService->fusionarCarritoInvitado($tokenInvitado, $this->clienteWeb);

        $this->assertCount(1, $carritoCliente->items);
        $this->assertEquals(1, $carritoCliente->items->first()->cantidad);

        // El carrito de invitado ya no debe existir
        $this->assertNull(Carrito::where('token', $tokenInvitado)->first());
    }

    public function test_stock_de_web_y_pos_es_el_mismo(): void
    {
        // 1. Stock visible en web inicialmente es 2
        $detalleInicial = $this->catalogoWebService->obtenerPorSlug('dell-inspiron-15');
        $this->assertEquals(2, $detalleInicial->stock_disponible);

        // 2. Vender una unidad en el POS físico con cliente identificado (boleta >= 700)
        $clientePos = Cliente::create([
            'tipo_documento' => TipoDocumentoCliente::DNI,
            'numero_documento' => '44556677',
            'nombre_razon_social' => 'Carlos Pos Test',
            'email' => 'carlos.pos@example.com',
            'activo' => true,
        ]);

        app(CajaService::class)->abrir($this->vendedor, 500.00);
        $this->ventaService->registrar(
            datos: [
                'cliente_id' => $clientePos->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                ['producto_id' => $this->laptop->id, 'serie_id' => $this->serie1->id, 'cantidad' => 1],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 2500.00],
            ],
            vendedor: $this->vendedor
        );

        // 3. El stock visible en la web se reduce inmediatamente a 1
        $detalleFinal = $this->catalogoWebService->obtenerPorSlug('dell-inspiron-15');
        $this->assertEquals(1, $detalleFinal->stock_disponible);
    }
}
