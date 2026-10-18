<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoSerie;
use App\Enums\MetodoPago;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDocumentoCliente;
use App\Enums\TipoMovimiento;
use App\Livewire\Pos\Venta as PosVentaComponent;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\User;
use App\Models\Venta;
use App\Services\CajaService;
use App\Services\VentaService;
use Database\Seeders\ConfiguracionesSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\SeriesComprobantesYClientesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendedor;

    protected User $vendedor2;

    protected User $almacen;

    protected Cliente $clienteGenerico;

    protected Cliente $clienteDni;

    protected Cliente $clienteRuc;

    protected Producto $productoConSeries;

    protected Producto $productoSinSeries;

    protected Serie $serieDisponible;

    protected CajaService $cajaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->seed(SeriesComprobantesYClientesSeeder::class);
        $this->seed(ConfiguracionesSeeder::class);

        $this->cajaService = app(CajaService::class);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@tienda.com']);
        $this->admin->assignRole('admin');

        $this->vendedor = User::factory()->create(['name' => 'Vendedor 1', 'email' => 'vendedor1@tienda.com']);
        $this->vendedor->assignRole('vendedor');

        $this->vendedor2 = User::factory()->create(['name' => 'Vendedor 2', 'email' => 'vendedor2@tienda.com']);
        $this->vendedor2->assignRole('vendedor');

        $this->almacen = User::factory()->create(['name' => 'Almacen User', 'email' => 'almacen@tienda.com']);
        $this->almacen->assignRole('almacen');

        $this->clienteGenerico = Cliente::where('tipo_documento', TipoDocumentoCliente::SIN_DOC->value)->firstOrFail();

        $this->clienteDni = Cliente::create([
            'tipo_documento' => TipoDocumentoCliente::DNI,
            'numero_documento' => '12345678',
            'nombre_razon_social' => 'Juan Perez',
            'activo' => true,
        ]);

        $this->clienteRuc = Cliente::create([
            'tipo_documento' => TipoDocumentoCliente::RUC,
            'numero_documento' => '20601234568',
            'nombre_razon_social' => 'EMPRESA PERU S.A.C.',
            'activo' => true,
        ]);

        $categoria = Categoria::create(['nombre' => 'Laptops', 'slug' => 'laptops']);
        $marca = Marca::create(['nombre' => 'Dell', 'slug' => 'dell']);

        $this->productoConSeries = Producto::create([
            'sku' => 'LAP-DELL-01',
            'nombre' => 'Laptop Dell Inspiron 15',
            'slug' => 'laptop-dell-inspiron-15',
            'categoria_id' => $categoria->id,
            'marca_id' => $marca->id,
            'precio_compra_referencial' => 1500.00,
            'precio_venta' => 2000.00,
            'incluye_igv' => true,
            'controla_series' => true,
            'activo' => true,
        ]);

        $this->serieDisponible = Serie::create([
            'producto_id' => $this->productoConSeries->id,
            'numero_serie' => 'SN-POS-001',
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => 1500.00,
            'fecha_ingreso' => now(),
        ]);

        $this->productoSinSeries = Producto::create([
            'sku' => 'ACC-MOUSE-01',
            'nombre' => 'Mouse Inalámbrico Logitech',
            'slug' => 'mouse-inalambrico-logitech',
            'categoria_id' => $categoria->id,
            'marca_id' => $marca->id,
            'precio_compra_referencial' => 30.00,
            'precio_venta' => 50.00,
            'incluye_igv' => true,
            'controla_series' => false,
            'activo' => true,
        ]);

        MovimientoInventario::create([
            'producto_id' => $this->productoSinSeries->id,
            'serie_id' => null,
            'tipo' => TipoMovimiento::INGRESO,
            'cantidad' => 20,
            'stock_resultante' => 20,
            'motivo' => 'Stock inicial de prueba',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_vendedor_sin_caja_abierta_no_puede_cobrar_y_se_le_exige_abrir_caja(): void
    {
        Livewire::actingAs($this->vendedor)
            ->test(PosVentaComponent::class)
            ->assertSet('mostrarModalAbrirCaja', true)
            ->call('abrirModalCobro')
            ->assertSet('mostrarModalCobro', false)
            ->assertSee('El carrito está vacío');
    }

    public function test_agregar_serie_por_escaneo_al_carrito(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);

        Livewire::actingAs($this->vendedor)
            ->test(PosVentaComponent::class)
            ->set('busqueda', 'SN-POS-001')
            ->call('procesarBusquedaEnter')
            ->assertCount('carrito', 1)
            ->assertSet('carrito.0.serie_id', $this->serieDisponible->id)
            ->assertSet('carrito.0.subtotal', 2000.00)
            ->assertSet('busqueda', '');
    }

    public function test_serie_ya_en_carrito_no_se_agrega_dos_veces(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);

        Livewire::actingAs($this->vendedor)
            ->test(PosVentaComponent::class)
            ->set('busqueda', 'SN-POS-001')
            ->call('procesarBusquedaEnter')
            ->assertCount('carrito', 1)
            ->set('busqueda', 'SN-POS-001')
            ->call('procesarBusquedaEnter')
            ->assertCount('carrito', 1)
            ->assertSee('ya se encuentra agregada en el carrito');
    }

    public function test_pago_insuficiente_bloquea_la_confirmacion(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);

        Livewire::actingAs($this->vendedor)
            ->test(PosVentaComponent::class)
            ->set('busqueda', 'SN-POS-001')
            ->call('procesarBusquedaEnter')
            ->set('clienteId', $this->clienteDni->id)
            ->call('abrirModalCobro')
            ->assertSet('mostrarModalCobro', true)
            ->set('pagos.0.monto', '1000.00') // Total es 2000, falta 1000
            ->call('confirmarVenta')
            ->assertSee('Los métodos de pago no cubren el total')
            ->assertCount('carrito', 1); // No pierde el carrito
    }

    public function test_cobro_exitoso_crea_la_venta_y_limpia_el_carrito(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);

        $component = Livewire::actingAs($this->vendedor)
            ->test(PosVentaComponent::class)
            ->set('busqueda', 'SN-POS-001')
            ->call('procesarBusquedaEnter')
            ->set('clienteId', $this->clienteDni->id)
            ->set('tipoComprobante', 'boleta')
            ->call('abrirModalCobro')
            ->assertSet('mostrarModalCobro', true)
            ->set('pagos.0.monto', '2000.00')
            ->set('efectivoRecibido', '2000.00')
            ->call('confirmarVenta')
            ->assertSet('mostrarModalCobro', false)
            ->assertCount('carrito', 0)
            ->assertSee('¡Venta Registrada!');

        $this->assertNotNull($component->get('ventaExitosa'));

        $this->assertDatabaseHas('ventas', [
            'vendedor_id' => $this->vendedor->id,
            'cliente_id' => $this->clienteDni->id,
            'total' => 2000.00,
        ]);

        $this->assertEquals(EstadoSerie::VENDIDA, $this->serieDisponible->fresh()->estado);
    }

    public function test_almacen_no_accede_al_pos(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('pos.index'))
            ->assertStatus(403);
    }

    public function test_vendedor_no_accede_al_panel_de_filament(): void
    {
        $this->actingAs($this->vendedor)
            ->get('/admin')
            ->assertStatus(403);
    }

    public function test_vendedor_no_ve_ventas_ajenas_en_su_historial(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);
        $this->cajaService->abrir($this->vendedor2, 100.00);

        $serie2 = Serie::create([
            'producto_id' => $this->productoConSeries->id,
            'numero_serie' => 'SN-POS-VEND2',
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => 1500.00,
            'fecha_ingreso' => now(),
        ]);

        // Venta de vendedor 2
        $ventaService = app(VentaService::class);
        $venta2 = $ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie2->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 2000.00],
            ],
            vendedor: $this->vendedor2
        );

        // Vendedor 1 abre historial en POS y NO debe ver la venta2
        Livewire::actingAs($this->vendedor)
            ->test(PosVentaComponent::class)
            ->set('mostrarModalHistorial', true)
            ->assertDontSee($venta2->codigo);
    }
}
