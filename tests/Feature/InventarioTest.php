<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoCompra;
use App\Enums\EstadoSerie;
use App\Enums\TipoComprobante;
use App\Enums\TipoDocumento;
use App\Enums\TipoMovimiento;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Serie;
use App\Models\User;
use App\Services\CategoriaService;
use App\Services\CompraService;
use App\Services\InventarioService;
use App\Services\MarcaService;
use App\Services\ProductoService;
use App\Services\ProveedorService;
use BadMethodCallException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventarioTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $almacenero;

    protected Categoria $categoria;

    protected Marca $marca;

    protected Producto $productoConSeries;

    protected Producto $productoSinSeries;

    protected Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $rolAdmin = Role::create(['name' => 'admin']);
        $rolAlmacen = Role::create(['name' => 'almacen']);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole($rolAdmin);

        $this->almacenero = User::create([
            'name' => 'Almacen User',
            'email' => 'almacen@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->almacenero->assignRole($rolAlmacen);

        $this->categoria = Categoria::create([
            'nombre' => 'Laptops',
            'slug' => 'laptops',
            'activo' => true,
        ]);

        $this->marca = Marca::create([
            'nombre' => 'ASUS',
            'slug' => 'asus',
            'activo' => true,
        ]);

        $this->productoConSeries = Producto::create([
            'sku' => 'LAP-ASUS-001',
            'nombre' => 'Laptop ASUS ROG',
            'slug' => 'laptop-asus-rog',
            'categoria_id' => $this->categoria->id,
            'marca_id' => $this->marca->id,
            'precio_compra_referencial' => 3500.00,
            'precio_venta' => 4500.00,
            'controla_series' => true,
            'activo' => true,
        ]);

        $this->productoSinSeries = Producto::create([
            'sku' => 'ACC-CAB-001',
            'nombre' => 'Cable HDMI 2.1',
            'slug' => 'cable-hdmi-2-1',
            'categoria_id' => $this->categoria->id,
            'marca_id' => $this->marca->id,
            'precio_compra_referencial' => 15.00,
            'precio_venta' => 35.00,
            'controla_series' => false,
            'activo' => true,
        ]);

        $this->proveedor = Proveedor::create([
            'tipo_documento' => TipoDocumento::RUC,
            'numero_documento' => '20123456789',
            'razon_social' => 'Distribuidora Tech SAC',
            'activo' => true,
        ]);
    }

    public function test_borrador_calcula_subtotal_igv_y_total_correctamente(): void
    {
        $compraService = new CompraService;

        $detalles = [
            [
                'producto_id' => $this->productoConSeries->id,
                'cantidad' => 2,
                'costo_unitario' => 1000.00, // subtotal: 2000
            ],
            [
                'producto_id' => $this->productoSinSeries->id,
                'cantidad' => 10,
                'costo_unitario' => 10.00, // subtotal: 100
            ],
        ];

        $compra = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00001',
            'fecha_emision' => '2026-10-18',
            'moneda' => 'PEN',
        ], $detalles, $this->admin);

        // Subtotal esperado: 2100.00, IGV 18%: 378.00, Total: 2478.00
        $this->assertEquals('2100.00', $compra->subtotal);
        $this->assertEquals('378.00', $compra->igv);
        $this->assertEquals('2478.00', $compra->total);
        $this->assertEquals(EstadoCompra::BORRADOR, $compra->estado);
        $this->assertEquals('COM-000001', $compra->codigo);
    }

    public function test_codigos_correlativos_sin_duplicados(): void
    {
        $compraService = new CompraService;

        $c1 = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00001',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoConSeries->id, 'cantidad' => 1, 'costo_unitario' => 100],
        ], $this->admin);

        $c2 = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00002',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoConSeries->id, 'cantidad' => 1, 'costo_unitario' => 100],
        ], $this->admin);

        $this->assertEquals('COM-000001', $c1->codigo);
        $this->assertEquals('COM-000002', $c2->codigo);
    }

    public function test_recibir_crea_exactamente_n_series_y_n_movimientos(): void
    {
        $compraService = new CompraService;
        $inventarioService = new InventarioService;

        $compra = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00010',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoConSeries->id, 'cantidad' => 3, 'costo_unitario' => 1500.00],
        ], $this->admin);

        $detalle = $compra->detalles->first();

        $series = ['SN-ROG-001', 'SN-ROG-002', 'SN-ROG-003'];
        $compraRecibida = $inventarioService->recibirCompra($compra, [
            $detalle->id => $series,
        ], $this->almacenero);

        $this->assertEquals(EstadoCompra::RECIBIDA, $compraRecibida->estado);
        $this->assertEquals($this->almacenero->id, $compraRecibida->recibido_por);
        $this->assertNotNull($compraRecibida->recibido_en);

        $this->assertCount(3, Serie::where('producto_id', $this->productoConSeries->id)->get());
        $this->assertEquals(3, $inventarioService->stockDisponible($this->productoConSeries));

        $movimientos = MovimientoInventario::where('producto_id', $this->productoConSeries->id)->get();
        $this->assertCount(3, $movimientos);

        foreach ($movimientos as $mov) {
            $this->assertEquals(TipoMovimiento::INGRESO, $mov->tipo);
            $this->assertEquals(1, $mov->cantidad);
            $this->assertNotNull($mov->serie_id);
            $this->assertEquals($this->almacenero->id, $mov->user_id);
        }
    }

    public function test_rechaza_cantidad_de_series_diferente_a_cantidad_del_detalle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no coincide con la cantidad del detalle');

        $compraService = new CompraService;
        $inventarioService = new InventarioService;

        $compra = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00011',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoConSeries->id, 'cantidad' => 3, 'costo_unitario' => 1500.00],
        ], $this->admin);

        $detalle = $compra->detalles->first();

        // Enviamos solo 2 series en vez de 3
        $inventarioService->recibirCompra($compra, [
            $detalle->id => ['SN-ROG-001', 'SN-ROG-002'],
        ], $this->almacenero);
    }

    public function test_rechaza_series_duplicadas_en_el_envio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('está duplicada en el envío');

        $compraService = new CompraService;
        $inventarioService = new InventarioService;

        $compra = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00012',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoConSeries->id, 'cantidad' => 2, 'costo_unitario' => 1500.00],
        ], $this->admin);

        $detalle = $compra->detalles->first();

        // Serie duplicada en el payload
        $inventarioService->recibirCompra($compra, [
            $detalle->id => ['SN-ROG-001', 'sn-rog-001'],
        ], $this->almacenero);
    }

    public function test_rechaza_series_duplicadas_contra_la_bd(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ya se encuentra registrada en la base de datos');

        $compraService = new CompraService;
        $inventarioService = new InventarioService;

        // Serie ya existente en BD
        Serie::create([
            'producto_id' => $this->productoConSeries->id,
            'numero_serie' => 'SN-EXISTENTE-01',
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => 1000,
            'fecha_ingreso' => now(),
        ]);

        $compra = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00013',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoConSeries->id, 'cantidad' => 1, 'costo_unitario' => 1500.00],
        ], $this->admin);

        $detalle = $compra->detalles->first();

        $inventarioService->recibirCompra($compra, [
            $detalle->id => ['sn-existente-01'],
        ], $this->almacenero);
    }

    public function test_normaliza_series_trim_y_mayusculas(): void
    {
        $compraService = new CompraService;
        $inventarioService = new InventarioService;

        $compra = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00014',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoConSeries->id, 'cantidad' => 1, 'costo_unitario' => 1500.00],
        ], $this->admin);

        $detalle = $compra->detalles->first();

        $inventarioService->recibirCompra($compra, [
            $detalle->id => ['   abc-xyz-123   '],
        ], $this->almacenero);

        $serie = Serie::where('producto_id', $this->productoConSeries->id)->first();
        $this->assertEquals('ABC-XYZ-123', $serie->numero_serie);
    }

    public function test_no_se_puede_recibir_dos_veces(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Solo se pueden recibir compras en estado borrador');

        $compraService = new CompraService;
        $inventarioService = new InventarioService;

        $compra = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00015',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoSinSeries->id, 'cantidad' => 5, 'costo_unitario' => 10.00],
        ], $this->admin);

        // Primera recepción (éxito)
        $inventarioService->recibirCompra($compra, [], $this->almacenero);

        // Segunda recepción (debe fallar)
        $inventarioService->recibirCompra($compra->fresh(), [], $this->almacenero);
    }

    public function test_rollback_total_si_falla_a_mitad_de_recepcion(): void
    {
        $compraService = new CompraService;
        $inventarioService = new InventarioService;

        $compra = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00016',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoConSeries->id, 'cantidad' => 2, 'costo_unitario' => 1500.00],
        ], $this->admin);

        $detalle = $compra->detalles->first();

        // El segundo número de serie está duplicado para forzar excepción
        try {
            $inventarioService->recibirCompra($compra, [
                $detalle->id => ['SN-ROLLBACK-01', 'SN-ROLLBACK-01'],
            ], $this->almacenero);
            $this->fail('Se esperaba excepción por serie duplicada.');
        } catch (InvalidArgumentException $e) {
            // Verificamos rollback
            $this->assertEquals(0, Serie::where('producto_id', $this->productoConSeries->id)->count());
            $this->assertEquals(0, MovimientoInventario::where('producto_id', $this->productoConSeries->id)->count());
            $this->assertEquals(EstadoCompra::BORRADOR, $compra->fresh()->estado);
        }
    }

    public function test_anular_recibida_con_series_disponibles_pasa_series_a_baja(): void
    {
        $compraService = new CompraService;
        $inventarioService = new InventarioService;

        $compra = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00017',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoConSeries->id, 'cantidad' => 2, 'costo_unitario' => 1500.00],
        ], $this->admin);

        $detalle = $compra->detalles->first();

        $inventarioService->recibirCompra($compra, [
            $detalle->id => ['SN-ANULAR-01', 'SN-ANULAR-02'],
        ], $this->almacenero);

        $this->assertEquals(2, $inventarioService->stockDisponible($this->productoConSeries));

        $compraAnulada = $compraService->anular($compra->fresh(), 'Error en factura del proveedor', $this->admin);

        $this->assertEquals(EstadoCompra::ANULADA, $compraAnulada->estado);
        $this->assertEquals(0, $inventarioService->stockDisponible($this->productoConSeries));

        $series = Serie::where('producto_id', $this->productoConSeries->id)->get();
        foreach ($series as $serie) {
            $this->assertEquals(EstadoSerie::BAJA, $serie->estado);
        }

        $movimientosAnulacion = MovimientoInventario::where('tipo', TipoMovimiento::ANULACION)
            ->orderBy('id', 'asc')
            ->get();
        $this->assertCount(2, $movimientosAnulacion);
        $this->assertEquals(-1, $movimientosAnulacion[0]->cantidad);
        $this->assertEquals(1, $movimientosAnulacion[0]->stock_resultante);
        $this->assertEquals(-1, $movimientosAnulacion[1]->cantidad);
        $this->assertEquals(0, $movimientosAnulacion[1]->stock_resultante);
    }

    public function test_anular_con_una_serie_vendida_es_rechazado(): void
    {
        $compraService = new CompraService;
        $inventarioService = new InventarioService;

        $compra = $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-00018',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoConSeries->id, 'cantidad' => 2, 'costo_unitario' => 1500.00],
        ], $this->admin);

        $detalle = $compra->detalles->first();

        $inventarioService->recibirCompra($compra, [
            $detalle->id => ['SN-VENDER-01', 'SN-VENDER-02'],
        ], $this->almacenero);

        // Simulamos que una de las series se vendió
        $serieVendida = Serie::where('numero_serie', 'SN-VENDER-01')->first();
        $serieVendida->update(['estado' => EstadoSerie::VENDIDA]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya no está disponible');

        $compraService->anular($compra->fresh(), 'Intento de anulación', $this->admin);
    }

    public function test_transiciones_invalidas_rechazadas_en_estado_serie(): void
    {
        $this->assertTrue(EstadoSerie::DISPONIBLE->puedePasarA(EstadoSerie::RESERVADA));
        $this->assertTrue(EstadoSerie::DISPONIBLE->puedePasarA(EstadoSerie::VENDIDA));
        $this->assertTrue(EstadoSerie::DISPONIBLE->puedePasarA(EstadoSerie::BAJA));

        $this->assertFalse(EstadoSerie::DISPONIBLE->puedePasarA(EstadoSerie::EN_GARANTIA));
        $this->assertFalse(EstadoSerie::BAJA->puedePasarA(EstadoSerie::DISPONIBLE));
        $this->assertFalse(EstadoSerie::VENDIDA->puedePasarA(EstadoSerie::DISPONIBLE));

        $inventarioService = new InventarioService;
        $serie = Serie::create([
            'producto_id' => $this->productoConSeries->id,
            'numero_serie' => 'SN-TRANS-01',
            'estado' => EstadoSerie::BAJA,
            'costo' => 1000,
            'fecha_ingreso' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transición inválida');

        $inventarioService->ajustarEstado($serie, EstadoSerie::DISPONIBLE, 'Reactiva serie', $this->admin);
    }

    public function test_stock_disponible_cuenta_solo_disponibles(): void
    {
        $inventarioService = new InventarioService;

        Serie::create([
            'producto_id' => $this->productoConSeries->id,
            'numero_serie' => 'SN-S1',
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => 1000,
            'fecha_ingreso' => now(),
        ]);

        Serie::create([
            'producto_id' => $this->productoConSeries->id,
            'numero_serie' => 'SN-S2',
            'estado' => EstadoSerie::VENDIDA,
            'costo' => 1000,
            'fecha_ingreso' => now(),
        ]);

        Serie::create([
            'producto_id' => $this->productoConSeries->id,
            'numero_serie' => 'SN-S3',
            'estado' => EstadoSerie::BAJA,
            'costo' => 1000,
            'fecha_ingreso' => now(),
        ]);

        $this->assertEquals(1, $inventarioService->stockDisponible($this->productoConSeries));
    }

    public function test_movimientos_de_inventario_no_se_pueden_editar_ni_eliminar(): void
    {
        $movimiento = MovimientoInventario::create([
            'producto_id' => $this->productoSinSeries->id,
            'serie_id' => null,
            'tipo' => TipoMovimiento::INGRESO,
            'cantidad' => 10,
            'stock_resultante' => 10,
            'user_id' => $this->admin->id,
        ]);

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('inmutables');
        $movimiento->update(['cantidad' => 20]);
    }

    public function test_movimientos_de_inventario_no_se_pueden_eliminar(): void
    {
        $movimiento = MovimientoInventario::create([
            'producto_id' => $this->productoSinSeries->id,
            'serie_id' => null,
            'tipo' => TipoMovimiento::INGRESO,
            'cantidad' => 10,
            'stock_resultante' => 10,
            'user_id' => $this->admin->id,
        ]);

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('inmutables');
        $movimiento->delete();
    }

    public function test_validaciones_de_proveedor_y_restriccion_de_eliminacion(): void
    {
        $proveedorService = new ProveedorService;

        // RUC inválido (no tiene 11 dígitos)
        try {
            $proveedorService->crear([
                'tipo_documento' => TipoDocumento::RUC,
                'numero_documento' => '12345',
                'razon_social' => 'Empresa Test',
            ]);
            $this->fail('Debe fallar por RUC inválido.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('11 dígitos', $e->getMessage());
        }

        // DNI inválido (no tiene 8 dígitos)
        try {
            $proveedorService->crear([
                'tipo_documento' => TipoDocumento::DNI,
                'numero_documento' => '123',
                'razon_social' => 'Persona Test',
            ]);
            $this->fail('Debe fallar por DNI inválido.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('8 dígitos', $e->getMessage());
        }

        // Proveedor con compras asociadas no se elimina, se desactiva
        $compraService = new CompraService;
        $compraService->crearBorrador([
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-99999',
            'fecha_emision' => '2026-10-18',
        ], [
            ['producto_id' => $this->productoSinSeries->id, 'cantidad' => 1, 'costo_unitario' => 10],
        ], $this->admin);

        try {
            $proveedorService->eliminar($this->proveedor);
            $this->fail('Debe rechazar la eliminación');
        } catch (InvalidArgumentException $e) {
            $this->assertFalse($this->proveedor->fresh()->activo);
            $this->assertNull($this->proveedor->fresh()->deleted_at);
        }
    }

    public function test_validaciones_de_producto_categoria_y_marca(): void
    {
        $productoService = new ProductoService;
        $categoriaService = new CategoriaService;
        $marcaService = new MarcaService;

        // Precio de venta <= 0 rechazado
        try {
            $productoService->crear([
                'sku' => 'TEST-001',
                'nombre' => 'Test Item',
                'categoria_id' => $this->categoria->id,
                'marca_id' => $this->marca->id,
                'precio_venta' => 0,
            ]);
            $this->fail('Debe fallar por precio de venta <= 0');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('mayor a cero', $e->getMessage());
        }

        // Categoría con productos no se elimina, se desactiva
        try {
            $categoriaService->eliminar($this->categoria);
            $this->fail('Debe rechazar eliminación de categoría');
        } catch (InvalidArgumentException $e) {
            $this->assertFalse($this->categoria->fresh()->activo);
        }

        // Marca con productos no se elimina, se desactiva
        try {
            $marcaService->eliminar($this->marca);
            $this->fail('Debe rechazar eliminación de marca');
        } catch (InvalidArgumentException $e) {
            $this->assertFalse($this->marca->fresh()->activo);
        }
    }
}
