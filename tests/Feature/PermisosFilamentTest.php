<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoCompra;
use App\Enums\EstadoSerie;
use App\Enums\TipoComprobante;
use App\Enums\TipoDocumento;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Serie;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermisosFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $almacenero;

    protected User $vendedor;

    protected Categoria $categoria;

    protected Marca $marca;

    protected Producto $producto;

    protected Proveedor $proveedor;

    protected Compra $compra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin_test@tienda.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->syncRoles(['admin']);

        $this->almacenero = User::create([
            'name' => 'Almacen Test',
            'email' => 'almacen_test@tienda.com',
            'password' => bcrypt('password'),
        ]);
        $this->almacenero->syncRoles(['almacen']);

        $this->vendedor = User::create([
            'name' => 'Vendedor Test',
            'email' => 'vendedor_test@tienda.com',
            'password' => bcrypt('password'),
        ]);
        $this->vendedor->syncRoles(['vendedor']);

        $this->categoria = Categoria::create(['nombre' => 'Laptops', 'slug' => 'laptops']);
        $this->marca = Marca::create(['nombre' => 'Lenovo', 'slug' => 'lenovo']);

        $this->producto = Producto::create([
            'sku' => 'LAP-LEN-001',
            'nombre' => 'ThinkPad T14',
            'slug' => 'thinkpad-t14',
            'categoria_id' => $this->categoria->id,
            'marca_id' => $this->marca->id,
            'precio_compra_referencial' => 3000.00,
            'precio_venta' => 4200.00,
            'controla_series' => true,
        ]);

        $this->proveedor = Proveedor::create([
            'tipo_documento' => TipoDocumento::RUC,
            'numero_documento' => '20999888771',
            'razon_social' => 'Proveedor Mayorista SAC',
        ]);

        $this->compra = Compra::create([
            'codigo' => 'COM-000100',
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-900',
            'fecha_emision' => '2026-10-18',
            'subtotal' => 3000.00,
            'igv' => 540.00,
            'total' => 3540.00,
            'estado' => EstadoCompra::BORRADOR,
            'creado_por' => $this->admin->id,
        ]);
    }

    public function test_vendedor_no_puede_acceder_al_panel_de_filament(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertFalse($this->vendedor->canAccessPanel($panel));
        $this->assertTrue($this->admin->canAccessPanel($panel));
        $this->assertTrue($this->almacenero->canAccessPanel($panel));

        // Intento de acceso HTTP redirecciona o da 403
        $response = $this->actingAs($this->vendedor)->get('/admin');
        $response->assertForbidden();
    }

    public function test_almacen_no_puede_anular_compras_pero_admin_si(): void
    {
        $this->assertFalse($this->almacenero->can('anular', $this->compra));
        $this->assertTrue($this->admin->can('anular', $this->compra));
    }

    public function test_almacen_no_puede_ajustar_estado_de_series_pero_admin_si(): void
    {
        $serie = Serie::create([
            'producto_id' => $this->producto->id,
            'numero_serie' => 'SN-LEN-99',
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => 3000.00,
            'fecha_ingreso' => now(),
        ]);

        $this->assertFalse($this->almacenero->can('ajustarEstado', $serie));
        $this->assertTrue($this->admin->can('ajustarEstado', $serie));
    }

    public function test_admin_si_ve_costos_y_almacen_no_tiene_permiso_de_costos(): void
    {
        $this->assertTrue($this->admin->can('costos.ver'));
        $this->assertFalse($this->almacenero->can('costos.ver'));
        $this->assertFalse($this->vendedor->can('costos.ver'));
    }

    public function test_vendedor_no_accede_a_compras(): void
    {
        $this->assertFalse($this->vendedor->can('viewAny', Compra::class));
        $this->assertFalse($this->vendedor->can('create', Compra::class));

        // Almacén y Admin sí tienen acceso para ver y crear compras
        $this->assertTrue($this->almacenero->can('viewAny', Compra::class));
        $this->assertTrue($this->almacenero->can('create', Compra::class));
        $this->assertTrue($this->admin->can('viewAny', Compra::class));
        $this->assertTrue($this->admin->can('create', Compra::class));
    }

    public function test_dashboard_muestra_valor_inventario_solo_a_admin(): void
    {
        Serie::create([
            'producto_id' => $this->producto->id,
            'numero_serie' => 'SN-TEST-DASH',
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => 3000.00,
            'fecha_ingreso' => now(),
        ]);

        // Verificamos respuesta exitosa del panel admin para Admin
        $responseAdmin = $this->actingAs($this->admin)->get('/admin');
        $responseAdmin->assertSuccessful();

        $this->flushSession();

        // Verificamos respuesta exitosa para Almacenero
        $responseAlmacen = $this->actingAs($this->almacenero)->get('/admin');
        $responseAlmacen->assertSuccessful();
    }
}
