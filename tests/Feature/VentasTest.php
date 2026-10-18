<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoCaja;
use App\Enums\EstadoSerie;
use App\Enums\EstadoVenta;
use App\Enums\MetodoPago;
use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDocumentoCliente;
use App\Enums\TipoMovimiento;
use App\Enums\TipoMovimientoCaja;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\SerieComprobante;
use App\Models\User;
use App\Models\Venta;
use App\Policies\VentaPolicy;
use App\Services\CajaService;
use App\Services\ClienteService;
use App\Services\InventarioService;
use App\Services\NumeracionService;
use App\Services\ReporteVentaService;
use App\Services\VentaService;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\SeriesComprobantesYClientesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class VentasTest extends TestCase
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

    protected VentaService $ventaService;

    protected CajaService $cajaService;

    protected ClienteService $clienteService;

    protected NumeracionService $numeracionService;

    protected ReporteVentaService $reporteService;

    protected InventarioService $inventarioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->seed(SeriesComprobantesYClientesSeeder::class);

        $this->cajaService = app(CajaService::class);
        $this->clienteService = app(ClienteService::class);
        $this->numeracionService = app(NumeracionService::class);
        $this->inventarioService = app(InventarioService::class);
        $this->ventaService = app(VentaService::class);
        $this->reporteService = app(ReporteVentaService::class);

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
            'numero_documento' => '20601234568', // RUC válido
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

        // Registrar stock inicial para producto sin series mediante movimiento de inventario
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

    protected function crearSerieDisponible(string $sn, float $costo = 1500.00): Serie
    {
        return Serie::create([
            'producto_id' => $this->productoConSeries->id,
            'numero_serie' => $sn,
            'estado' => EstadoSerie::DISPONIBLE,
            'costo' => $costo,
            'fecha_ingreso' => now(),
        ]);
    }

    public function test_no_se_puede_vender_sin_caja_abierta(): void
    {
        $serie = $this->crearSerieDisponible('SN-NOCAJA-01');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no tiene una sesión de caja abierta');

        $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteGenerico->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 2000.00],
            ],
            vendedor: $this->vendedor
        );
    }

    public function test_solo_una_caja_abierta_por_usuario(): void
    {
        $caja1 = $this->cajaService->abrir($this->vendedor, 100.00, 'Apertura turno mañana');
        $this->assertInstanceOf(CajaSesion::class, $caja1);
        $this->assertTrue($caja1->estaAbierta());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ya tiene una sesión de caja abierta');

        $this->cajaService->abrir($this->vendedor, 50.00, 'Segunda apertura');
    }

    public function test_venta_con_serie_disponible_actualiza_serie_y_movimiento_y_copia_costo(): void
    {
        $this->cajaService->abrir($this->vendedor, 200.00);
        $serie = $this->crearSerieDisponible('SN-VENTA-01', 1450.50);

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 2000.00],
            ],
            vendedor: $this->vendedor
        );

        $this->assertInstanceOf(Venta::class, $venta);
        $this->assertEquals(EstadoVenta::COMPLETADA, $venta->estado);
        $this->assertEquals(2000.00, (float) $venta->total);

        // Serie ahora debe estar vendida
        $serieRefrescada = $serie->fresh();
        $this->assertEquals(EstadoSerie::VENDIDA, $serieRefrescada->estado);

        // Detalle de venta guardó la copia del costo
        $detalle = $venta->detalles()->firstOrFail();
        $this->assertEquals(1450.50, (float) $detalle->costo_unitario);
        $this->assertEquals(2000.00, (float) $detalle->precio_unitario);
        $this->assertEquals($serie->id, $detalle->serie_id);

        // Movimiento de inventario tipo SALIDA
        $movimiento = MovimientoInventario::where('referencia_tipo', Venta::class)
            ->where('referencia_id', $venta->id)
            ->firstOrFail();

        $this->assertEquals(TipoMovimiento::SALIDA, $movimiento->tipo);
        $this->assertEquals(-1, $movimiento->cantidad);
        $this->assertEquals($serie->id, $movimiento->serie_id);

        // Movimiento de caja registrado
        $movCaja = CajaMovimiento::where('venta_id', $venta->id)->firstOrFail();
        $this->assertEquals(TipoMovimientoCaja::INGRESO, $movCaja->tipo);
        $this->assertEquals(2000.00, (float) $movCaja->monto);
    }

    public function test_serie_ya_vendida_o_reservada_es_rechazada(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);

        $serieVendida = Serie::create([
            'producto_id' => $this->productoConSeries->id,
            'numero_serie' => 'SN-VENDIDA-99',
            'estado' => EstadoSerie::VENDIDA,
            'costo' => 1500.00,
            'fecha_ingreso' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no está disponible para la venta');

        $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serieVendida->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 2000.00],
            ],
            vendedor: $this->vendedor
        );
    }

    public function test_misma_serie_dos_veces_en_la_venta_es_rechazada(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);
        $serie = $this->crearSerieDisponible('SN-DUPLICADA-01');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('está duplicada en la misma venta');

        $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 4000.00],
            ],
            vendedor: $this->vendedor
        );
    }

    public function test_dos_ventas_simultaneas_de_la_misma_serie_solo_una_gana(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);
        $this->cajaService->abrir($this->vendedor2, 100.00);

        $serie = $this->crearSerieDisponible('SN-CONCURRENTE-01');

        // Primera venta exitosa
        $venta1 = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 2000.00],
            ],
            vendedor: $this->vendedor
        );

        $this->assertInstanceOf(Venta::class, $venta1);

        // Segunda venta concurrente debe fallar
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no está disponible para la venta');

        $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 2000.00],
            ],
            vendedor: $this->vendedor2
        );
    }

    public function test_pagos_que_no_suman_el_total_son_rechazados(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);
        $serie = $this->crearSerieDisponible('SN-PAGO-01');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('es menor al total de la venta');

        $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 1500.00], // Faltan 500
            ],
            vendedor: $this->vendedor
        );
    }

    public function test_calculo_de_igv_descuento_y_total_correcto(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);

        // Venta de 2 mouses a 50 c/u = 100 total
        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteGenerico->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoSinSeries->id,
                    'cantidad' => 2,
                    'precio_unitario' => 50.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::YAPE, 'monto' => 100.00],
            ],
            vendedor: $this->vendedor
        );

        $this->assertEquals(100.00, (float) $venta->total);
        $this->assertEquals(84.75, (float) $venta->subtotal); // 100 / 1.18 = 84.745... -> 84.75
        $this->assertEquals(15.25, (float) $venta->igv);      // 100 - 84.75 = 15.25
        $this->assertEquals(0.00, (float) $venta->descuento);
    }

    public function test_factura_sin_ruc_es_rechazada(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);
        $serie = $this->crearSerieDisponible('SN-FACTURA-01');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La Factura exige que el cliente tenga RUC válido');

        $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id, // Cliente con DNI, no RUC
                'tipo_comprobante' => TipoComprobanteVenta::FACTURA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::TRANSFERENCIA, 'monto' => 2000.00],
            ],
            vendedor: $this->vendedor
        );
    }

    public function test_boleta_mayor_o_igual_a_700_sin_dni_es_rechazada(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);
        $serie = $this->crearSerieDisponible('SN-BOLETA700-01');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exigen identificar al cliente con DNI u otro documento');

        $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteGenerico->id, // SIN_DOC
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00, // >= 700
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 2000.00],
            ],
            vendedor: $this->vendedor
        );
    }

    public function test_descuento_mayor_al_permitido_para_vendedor_es_rechazado(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);
        $serie = $this->crearSerieDisponible('SN-DESCUENTO-01');

        // Vendedor intenta dar 10% de descuento (S/ 200 sobre 2000)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('supera el máximo permitido para vendedores (5%)');

        $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                    'descuento' => 200.00, // 10%
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 1800.00],
            ],
            vendedor: $this->vendedor
        );
    }

    public function test_admin_puede_aplicar_descuento_mayor_al_5_porciento(): void
    {
        $this->cajaService->abrir($this->admin, 500.00);
        $serie = $this->crearSerieDisponible('SN-ADMIN-DESC-01');

        // Admin aplica 15% de descuento (S/ 300 sobre 2000 = 1700)
        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                    'descuento' => 300.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 1700.00],
            ],
            vendedor: $this->admin
        );

        $this->assertEquals(1700.00, (float) $venta->total);
        $this->assertEquals(EstadoVenta::COMPLETADA, $venta->estado);
    }

    public function test_correlativos_sin_saltos_ni_duplicados(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);

        $numeros = [];
        for ($i = 1; $i <= 5; $i++) {
            $corr = $this->numeracionService->obtenerSiguienteCorrelativo(TipoComprobanteVenta::BOLETA, 'B001');
            $numeros[] = $corr['numero'];
        }

        $this->assertEquals([1, 2, 3, 4, 5], $numeros);

        $serieB001 = SerieComprobante::where('serie', 'B001')->firstOrFail();
        $this->assertEquals(5, $serieB001->correlativo_actual);
    }

    public function test_rollback_total_si_algo_falla_a_mitad_de_la_venta(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);
        $serie = $this->crearSerieDisponible('SN-ROLLBACK-01');

        $conteoVentasAntes = Venta::count();
        $conteoMovimientosAntes = MovimientoInventario::count();
        $conteoCajaAntes = CajaMovimiento::count();

        try {
            // Intentamos registrar con pagos insuficientes para provocar falla al final
            $this->ventaService->registrar(
                datos: [
                    'cliente_id' => $this->clienteGenerico->id,
                    'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
                ],
                items: [
                    [
                        'producto_id' => $this->productoConSeries->id,
                        'serie_id' => $serie->id,
                        'cantidad' => 1,
                        'precio_unitario' => 2000.00,
                    ],
                ],
                pagos: [
                    ['metodo' => MetodoPago::EFECTIVO, 'monto' => 100.00], // Menor a 2000
                ],
                vendedor: $this->vendedor
            );
        } catch (InvalidArgumentException $e) {
            // Esperado
        }

        // Serie debe permanecer en DISPONIBLE
        $this->assertEquals(EstadoSerie::DISPONIBLE, $serie->fresh()->estado);
        $this->assertEquals($conteoVentasAntes, Venta::count());
        $this->assertEquals($conteoMovimientosAntes, MovimientoInventario::count());
        $this->assertEquals($conteoCajaAntes, CajaMovimiento::count());
    }

    public function test_anular_venta_serie_vuelve_a_disponible_solo_admin_no_dos_veces(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);
        $serie = $this->crearSerieDisponible('SN-ANULAR-01');

        $venta = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 2000.00],
            ],
            vendedor: $this->vendedor
        );

        $this->assertEquals(EstadoSerie::VENDIDA, $serie->fresh()->estado);

        // Intento de anulación por vendedor (debe fallar)
        try {
            $this->ventaService->anular($venta, 'Intento vendedor', $this->vendedor);
            $this->fail('Vendedor no debería poder anular ventas');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Solo un usuario administrador', $e->getMessage());
        }

        // Anulación por admin
        $ventaAnulada = $this->ventaService->anular($venta, 'Cliente solicitó anulación inmediata', $this->admin);
        $this->assertEquals(EstadoVenta::ANULADA, $ventaAnulada->estado);
        $this->assertEquals($this->admin->id, $ventaAnulada->anulada_por);
        $this->assertEquals('Cliente solicitó anulación inmediata', $ventaAnulada->motivo_anulacion);

        // Serie debe haber vuelto a DISPONIBLE
        $this->assertEquals(EstadoSerie::DISPONIBLE, $serie->fresh()->estado);

        // Movimiento de anulación en inventario
        $movAnulacion = MovimientoInventario::where('referencia_tipo', Venta::class)
            ->where('referencia_id', $venta->id)
            ->where('tipo', TipoMovimiento::ANULACION)
            ->firstOrFail();
        $this->assertEquals(1, $movAnulacion->cantidad);

        // No se puede anular dos veces
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya se encuentra anulada');
        $this->ventaService->anular($ventaAnulada, 'Segunda anulación', $this->admin);
    }

    public function test_cierre_de_caja_calcula_esperado_y_diferencia(): void
    {
        $sesion = $this->cajaService->abrir($this->vendedor, 300.00);

        // Ingreso manual de 50 en efectivo
        $this->cajaService->registrarMovimiento(
            cajaSesion: $sesion,
            tipo: TipoMovimientoCaja::INGRESO,
            concepto: 'Cambio de billetes',
            monto: 50.00,
            metodoPago: MetodoPago::EFECTIVO,
            usuario: $this->vendedor
        );

        // Egreso manual de 20 en efectivo
        $this->cajaService->registrarMovimiento(
            cajaSesion: $sesion,
            tipo: TipoMovimientoCaja::EGRESO,
            concepto: 'Pago de fotocopias',
            monto: 20.00,
            metodoPago: MetodoPago::EFECTIVO,
            usuario: $this->vendedor
        );

        // Venta de 2 mouses pagados en efectivo: 100.00
        $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteGenerico->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoSinSeries->id,
                    'cantidad' => 2,
                    'precio_unitario' => 50.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 100.00],
            ],
            vendedor: $this->vendedor
        );

        // Esperado en efectivo: 300 (apertura) + 50 (ingreso) - 20 (egreso) + 100 (venta) = 430.00
        // Se cuenta dinero real: 420.00 (faltan 10)
        $sesionCerrada = $this->cajaService->cerrar($sesion, 420.00, $this->vendedor, 'Cuadre con faltante de 10');

        $this->assertEquals(EstadoCaja::CERRADA, $sesionCerrada->estado);
        $this->assertEquals(430.00, (float) $sesionCerrada->monto_cierre_esperado);
        $this->assertEquals(420.00, (float) $sesionCerrada->monto_cierre_real);
        $this->assertEquals(-10.00, (float) $sesionCerrada->diferencia);

        // Resumen
        $resumen = $this->cajaService->resumen($sesionCerrada);
        $this->assertEquals(430.00, $resumen['monto_cierre_esperado']);
        $this->assertEquals(-10.00, $resumen['diferencia']);
    }

    public function test_vendedor_no_ve_ventas_ajenas_ni_margenes(): void
    {
        $this->cajaService->abrir($this->vendedor, 100.00);
        $this->cajaService->abrir($this->vendedor2, 100.00);

        $serie1 = $this->crearSerieDisponible('SN-VEND1-01');
        $serie2 = $this->crearSerieDisponible('SN-VEND2-01');

        $venta1 = $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie1->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00,
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 2000.00],
            ],
            vendedor: $this->vendedor
        );

        $venta2 = $this->ventaService->registrar(
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

        // Policy de ventas
        $policy = app(VentaPolicy::class);
        $this->assertTrue($policy->view($this->vendedor, $venta1));
        $this->assertFalse($policy->view($this->vendedor, $venta2)); // Vendedor 1 NO puede ver venta de Vendedor 2
        $this->assertTrue($policy->view($this->admin, $venta1));    // Admin puede ver ambas
        $this->assertTrue($policy->view($this->admin, $venta2));

        // Restricción de reporte de márgenes
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No tienes permisos suficientes para visualizar márgenes');
        $this->reporteService->calcularMargen($this->vendedor);
    }

    public function test_admin_puede_calcular_margen_de_ventas(): void
    {
        $this->cajaService->abrir($this->admin, 500.00);
        $serie = $this->crearSerieDisponible('SN-MARGEN-01', 1200.00); // Costo: 1200

        $this->ventaService->registrar(
            datos: [
                'cliente_id' => $this->clienteDni->id,
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
            ],
            items: [
                [
                    'producto_id' => $this->productoConSeries->id,
                    'serie_id' => $serie->id,
                    'cantidad' => 1,
                    'precio_unitario' => 2000.00, // Venta: 2000
                ],
            ],
            pagos: [
                ['metodo' => MetodoPago::EFECTIVO, 'monto' => 2000.00],
            ],
            vendedor: $this->admin
        );

        $margen = $this->reporteService->calcularMargen($this->admin);
        $this->assertEquals(2000.00, $margen['total_ventas']);
        $this->assertEquals(1200.00, $margen['total_costo']);
        $this->assertEquals(800.00, $margen['margen_bruto']); // 2000 - 1200 = 800
        $this->assertEquals(40.0, $margen['porcentaje_margen']); // 800 / 2000 = 40%
        $this->assertEquals(1, $margen['cantidad_ventas']);
    }
}
