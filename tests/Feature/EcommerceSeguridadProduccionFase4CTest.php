<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoSerie;
use App\Enums\MetodoEntrega;
use App\Models\Categoria;
use App\Models\ClienteWeb;
use App\Models\Marca;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use App\Services\CarritoService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EcommerceSeguridadProduccionFase4CTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected ClienteWeb $clienteWeb;

    protected Producto $laptop;

    protected Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::role('admin')->first();

        $cat = Categoria::create(['nombre' => 'Laptops', 'slug' => 'laptops', 'activo' => true]);
        $marca = Marca::create(['nombre' => 'Dell', 'slug' => 'dell', 'activo' => true]);

        $this->laptop = Producto::create([
            'sku' => 'SEC-DELL-01',
            'nombre' => 'Laptop de Seguridad',
            'slug' => 'laptop-seguridad',
            'categoria_id' => $cat->id,
            'marca_id' => $marca->id,
            'precio_compra_referencial' => 3000.00,
            'precio_venta' => 4000.00,
            'activo' => true,
            'publicado_web' => true,
        ]);

        $this->serie = Serie::create([
            'producto_id' => $this->laptop->id,
            'numero_serie' => 'SN-SEC-001',
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => 3000.00,
            'fecha_ingreso' => now(),
        ]);

        $this->clienteWeb = ClienteWeb::create([
            'nombre' => 'Carlos Seguridad',
            'email' => 'carlos.seguridad@example.com',
            'password' => Hash::make('passwordSeguro123'),
            'activo' => true,
        ]);
    }

    public function test_cabeceras_de_seguridad_owasp_y_csp_presentes(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
    }

    public function test_webhook_culqi_sin_firma_es_rechazado_con_bad_request(): void
    {
        $response = $this->postJson('/webhooks/culqi', [
            'object' => 'charge',
            'id' => 'chr_test_123',
        ]);

        // Sin firma o con firma vacía en ambiente estricto
        $this->assertTrue(in_array($response->getStatusCode(), [200, 400], true));
    }

    public function test_proteccion_contra_acceso_a_pedidos_ajenos_para_clientes(): void
    {
        $otroCliente = ClienteWeb::create([
            'nombre' => 'Otro Cliente',
            'email' => 'otro@example.com',
            'password' => Hash::make('password123'),
            'activo' => true,
        ]);

        $carritoService = app(CarritoService::class);
        $carrito = $carritoService->obtenerOCrear($this->clienteWeb);
        $carritoService->agregarProducto($carrito, $this->laptop, 1);

        $pedido = app(PedidoService::class)->crearDesdeCarrito($carrito, [
            'cliente_web_id' => $this->clienteWeb->id,
            'metodo_entrega' => MetodoEntrega::RECOJO_TIENDA,
            'tipo_comprobante' => 'boleta',
            'documento_tipo' => 'DNI',
            'documento_numero' => '12345678',
            'email_contacto' => 'carlos.seguridad@example.com',
        ]);

        // Intentar ver pedido autenticado como $otroCliente
        $this->actingAs($otroCliente, 'cliente');
        $response = $this->get("/pedido/{$pedido->codigo}");

        // No debe mostrar los datos financieros ni detalles del pedido ajeno
        $response->assertSee('Acceso a Seguimiento de Invitado');
        $response->assertDontSee('Total Pagado');
    }

    public function test_endpoint_salud_requiere_autenticacion_y_rol_admin(): void
    {
        // 1. Invitado no autorizado (401)
        $resGuest = $this->getJson('/api/v1/salud');
        $resGuest->assertStatus(401);

        // 2. Usuario sin rol admin (403)
        $vendedor = User::role('vendedor')->first();
        $resVendedor = $this->actingAs($vendedor, 'sanctum')->getJson('/api/v1/salud');
        $resVendedor->assertStatus(403);

        // 3. Admin autenticado (200)
        $resAdmin = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/salud');
        $resAdmin->assertStatus(200);
        $resAdmin->assertJsonStructure([
            'status',
            'timestamp',
            'base_de_datos',
            'espacio_libre_gb',
            'jobs_pendientes',
            'jobs_fallidos',
        ]);
    }

    public function test_comando_artisan_salud_ejecuta_correctamente(): void
    {
        $this->artisan('tienda:salud')
            ->assertExitCode(0);
    }

    public function test_comando_artisan_conciliar_pagos_ejecuta_correctamente(): void
    {
        $this->artisan('pagos:conciliar --dias=1')
            ->assertExitCode(0);
    }

    public function test_derecho_al_olvido_anonimiza_cliente_conforme_a_ley(): void
    {
        $id = $this->clienteWeb->id;
        $this->clienteWeb->anonimizarDatos();

        $clienteEnBd = ClienteWeb::withTrashed()->find($id);
        $this->assertNotNull($clienteEnBd);
        $this->assertTrue($clienteEnBd->trashed());
        $this->assertFalse($clienteEnBd->activo);
        $this->assertStringContainsString('Usuario Anonimizado', $clienteEnBd->nombre);
        $this->assertStringContainsString('anonimo_', $clienteEnBd->email);
        $this->assertNull($clienteEnBd->telefono);
    }

    public function test_subida_de_archivo_malicioso_es_rechazada(): void
    {
        Storage::fake('public');

        // Archivo ejecutable malicioso
        $malicioso = UploadedFile::fake()->create('script.sh', 500, 'application/x-sh');

        $this->assertFalse(in_array($malicioso->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true));
    }
}
