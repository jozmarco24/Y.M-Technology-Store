<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoDocumento;
use App\Filament\Pages\Reportes\AjustesReportesPage;
use App\Filament\Pages\Reportes\CajaPage;
use App\Filament\Pages\Reportes\ComprasPage;
use App\Filament\Pages\Reportes\InventarioPage;
use App\Filament\Pages\Reportes\PostventaPage;
use App\Filament\Pages\Reportes\RentabilidadPage;
use App\Filament\Pages\Reportes\ResumenEjecutivoPage;
use App\Filament\Pages\Reportes\VendedoresPage;
use App\Filament\Pages\Reportes\VentasPage;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\Configuracion;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\ConfiguracionesSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\SeriesComprobantesYClientesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentReportesFase5BTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendedor;

    protected User $almacen;

    protected Producto $producto;

    protected Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->seed(ConfiguracionesSeeder::class);
        $this->seed(SeriesComprobantesYClientesSeeder::class);

        $this->admin = User::factory()->create([
            'name' => 'Admin Test',
            'email' => 'admin@test.pe',
        ]);
        $this->admin->assignRole('admin');

        $this->vendedor = User::factory()->create([
            'name' => 'Vendedor Test',
            'email' => 'vendedor@test.pe',
        ]);
        $this->vendedor->assignRole('vendedor');

        $this->almacen = User::factory()->create([
            'name' => 'Almacen Test',
            'email' => 'almacen@test.pe',
        ]);
        $this->almacen->assignRole('almacen');

        $categoria = Categoria::create(['nombre' => 'Componentes', 'slug' => 'componentes']);
        $marca = Marca::create(['nombre' => 'Corsair', 'slug' => 'corsair']);

        $this->producto = Producto::create([
            'categoria_id' => $categoria->id,
            'marca_id' => $marca->id,
            'nombre' => 'Memoria RAM 16GB',
            'sku' => 'RAM-16GB-RGB',
            'slug' => 'memoria-ram-16gb',
            'precio_venta' => 350.00,
            'precio_compra_referencial' => 250.00,
            'stock_minimo' => 5,
            'controla_series' => true,
            'activo' => true,
        ]);

        $this->proveedor = Proveedor::create([
            'tipo_documento' => TipoDocumento::RUC,
            'numero_documento' => '20609988771',
            'razon_social' => 'Tech Supply Perú SAC',
            'email' => 'ventas@techsupply.pe',
            'telefono' => '998877665',
            'direccion' => 'Calle Los Negocios 450, Surquillo',
        ]);
    }

    public function test_can_access_permisos_por_rol(): void
    {
        // Admin accede a todo
        $this->actingAs($this->admin);
        $this->assertTrue(ResumenEjecutivoPage::canAccess());
        $this->assertTrue(RentabilidadPage::canAccess());
        $this->assertTrue(InventarioPage::canAccess());
        $this->assertTrue(VentasPage::canAccess());
        $this->assertTrue(ComprasPage::canAccess());
        $this->assertTrue(PostventaPage::canAccess());
        $this->assertTrue(CajaPage::canAccess());
        $this->assertTrue(VendedoresPage::canAccess());
        $this->assertTrue(AjustesReportesPage::canAccess());

        // Almacén solo puede acceder a Inventario y Compras (según sus permisos)
        $this->actingAs($this->almacen);
        $this->assertFalse(ResumenEjecutivoPage::canAccess());
        $this->assertFalse(RentabilidadPage::canAccess());
        $this->assertTrue(InventarioPage::canAccess());
        $this->assertTrue(ComprasPage::canAccess());
        $this->assertFalse(AjustesReportesPage::canAccess());

        // Vendedor sin admin no tiene acceso a Rentabilidad ni Resumen Ejecutivo
        $this->actingAs($this->vendedor);
        $this->assertFalse(ResumenEjecutivoPage::canAccess());
        $this->assertFalse(RentabilidadPage::canAccess());
        $this->assertFalse(AjustesReportesPage::canAccess());
    }

    public function test_rentabilidad_page_muestra_datos_a_admin(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(RentabilidadPage::class)
            ->assertSuccessful()
            ->assertSee('Rentabilidad Detallada por Producto')
            ->assertSee('Top 10 Productos Más Rentables');
    }

    public function test_filtros_de_fecha_y_canal_cambian_estado_del_formulario(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(VentasPage::class)
            ->set('filtroData.rango_rapido', '7_dias')
            ->set('filtroData.canal', 'pos')
            ->call('aplicarFiltros')
            ->assertSet('canal', 'pos')
            ->assertHasNoErrors();
    }

    public function test_crear_compra_borrador_desde_reposicion_genera_la_compra(): void
    {
        $this->actingAs($this->admin);

        // Sin stock disponible y con stock_minimo = 5, el producto requiere reposición
        $comprasAntes = Compra::count();

        Livewire::test(InventarioPage::class)
            ->call('crearCompraBorrador')
            ->assertHasNoErrors();

        $this->assertGreaterThan($comprasAntes, Compra::count());

        $ultimaCompra = Compra::latest('id')->first();
        $this->assertNotNull($ultimaCompra);
        $this->assertEquals('borrador', $ultimaCompra->estado->value);
        $this->assertStringStartsWith('SUG-', (string) $ultimaCompra->numero_comprobante);
    }

    public function test_ajustes_reportes_solo_editables_por_admin(): void
    {
        $this->actingAs($this->almacen);
        $this->assertFalse(AjustesReportesPage::canAccess());

        $this->actingAs($this->admin);
        $this->assertTrue(AjustesReportesPage::canAccess());

        Livewire::test(AjustesReportesPage::class)
            ->set('data.dias_stock_muerto', 120)
            ->set('data.umbral_margen_bajo', 15.0)
            ->set('data.activar_comisiones', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertEquals('120', Configuracion::obtener('dias_stock_muerto'));
        $this->assertEquals('15', Configuracion::obtener('umbral_margen_bajo'));
        $this->assertEquals('1', Configuracion::obtener('activar_comisiones'));
    }

    public function test_resumen_ejecutivo_page_permite_exportar_csv_y_pdf(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(ResumenEjecutivoPage::class);

        // CSV export
        $responseCsv = $component->instance()->exportarCsv();
        $this->assertNotNull($responseCsv);
        $this->assertEquals(200, $responseCsv->getStatusCode());

        // PDF export
        $responsePdf = $component->instance()->exportarPdf();
        $this->assertNotNull($responsePdf);
    }
}
