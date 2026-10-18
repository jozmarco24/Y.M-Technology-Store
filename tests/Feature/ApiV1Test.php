<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoSerie;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendedor;

    protected Producto $producto;

    protected Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin API',
            'email' => 'admin_api@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->syncRoles(['admin']);

        $this->vendedor = User::create([
            'name' => 'Vendedor API',
            'email' => 'vendedor_api@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->vendedor->syncRoles(['vendedor']);

        $cat = Categoria::create(['nombre' => 'Laptops', 'slug' => 'laptops']);
        $marca = Marca::create(['nombre' => 'ASUS', 'slug' => 'asus']);

        $this->producto = Producto::create([
            'sku' => 'LAP-API-001',
            'nombre' => 'Laptop ASUS ZenBook',
            'slug' => 'laptop-asus-zenbook',
            'categoria_id' => $cat->id,
            'marca_id' => $marca->id,
            'precio_compra_referencial' => 2800.00,
            'precio_venta' => 3800.00,
            'controla_series' => true,
            'activo' => true,
        ]);

        $this->serie = Serie::create([
            'producto_id' => $this->producto->id,
            'numero_serie' => 'SN-ZEN-2026',
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => 2800.00,
            'fecha_ingreso' => now(),
        ]);
    }

    public function test_api_requiere_autenticacion_retorna_401_sin_token(): void
    {
        $response = $this->getJson('/api/v1/productos');
        $response->assertStatus(401);

        $responseShow = $this->getJson('/api/v1/productos/'.$this->producto->id);
        $responseShow->assertStatus(401);

        $responseSerie = $this->getJson('/api/v1/series/SN-ZEN-2026');
        $responseSerie->assertStatus(401);
    }

    public function test_productos_no_expone_costos_a_no_admin(): void
    {
        // Vendedor no tiene permiso costos.ver
        $tokenVendedor = $this->vendedor->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenVendedor)
            ->getJson('/api/v1/productos');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'sku',
                    'nombre',
                    'precio_venta',
                    'stock_disponible',
                ],
            ],
        ]);

        $item = $response->json('data.0');
        $this->assertArrayNotHasKey('precio_compra_referencial', $item);

        // En el detalle /productos/{id}
        $responseDetalle = $this->withHeader('Authorization', 'Bearer '.$tokenVendedor)
            ->getJson('/api/v1/productos/'.$this->producto->id);

        $responseDetalle->assertOk();
        $this->assertArrayNotHasKey('precio_compra_referencial', $responseDetalle->json('data'));
    }

    public function test_admin_si_recibe_costos_en_la_api(): void
    {
        $tokenAdmin = $this->admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenAdmin)
            ->getJson('/api/v1/productos/'.$this->producto->id);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('precio_compra_referencial', $data);
        $this->assertEquals(2800.00, $data['precio_compra_referencial']);
    }

    public function test_consulta_de_serie_existente_e_inexistente(): void
    {
        $token = $this->vendedor->createToken('test-token')->plainTextToken;

        // Serie existente
        $responseExistente = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/series/sn-zen-2026'); // Validamos normalización case-insensitive

        $responseExistente->assertOk();
        $responseExistente->assertJson([
            'data' => [
                'numero_serie' => 'SN-ZEN-2026',
                'estado' => 'disponible',
                'producto' => [
                    'id' => $this->producto->id,
                    'sku' => 'LAP-API-001',
                ],
            ],
        ]);
        // Vendedor no debe ver el costo en la serie
        $this->assertArrayNotHasKey('costo', $responseExistente->json('data'));

        // Limpiar estado de autenticación previo
        $this->flushSession();
        auth()->forgetGuards();

        // Con Admin sí debe verse el costo
        $tokenAdmin = $this->admin->createToken('admin-token')->plainTextToken;
        $responseAdminSerie = $this->withHeader('Authorization', 'Bearer '.$tokenAdmin)
            ->getJson('/api/v1/series/sn-zen-2026');
        $responseAdminSerie->assertOk();
        $this->assertArrayHasKey('costo', $responseAdminSerie->json('data'));
        $this->assertEquals(2800.00, $responseAdminSerie->json('data.costo'));

        // Serie inexistente
        $responseInexistente = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/series/SN-NO-EXISTE');

        $responseInexistente->assertStatus(404);
        $responseInexistente->assertJsonStructure(['mensaje']);
    }
}
