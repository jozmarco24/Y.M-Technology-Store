<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TipoComprobante;
use App\Enums\TipoDocumento;
use App\Models\User;
use App\Services\CategoriaService;
use App\Services\CompraService;
use App\Services\InventarioService;
use App\Services\MarcaService;
use App\Services\ProductoService;
use App\Services\ProveedorService;
use Illuminate\Database\Seeder;

class CatalogoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@tienda.com')->first()
            ?? User::factory()->create(['name' => 'Admin Demo', 'email' => 'admin@tienda.com']);

        $categoriaService = app(CategoriaService::class);
        $marcaService = app(MarcaService::class);
        $productoService = app(ProductoService::class);
        $proveedorService = app(ProveedorService::class);
        $compraService = app(CompraService::class);
        $inventarioService = app(InventarioService::class);

        // 1. 5 Categorías
        $catLaptops = $categoriaService->crear(['nombre' => 'Laptops', 'descripcion' => 'Portátiles para trabajo y gaming']);
        $catMonitores = $categoriaService->crear(['nombre' => 'Monitores', 'descripcion' => 'Pantallas IPS, OLED y alta tasa de refresco']);
        $catPerifericos = $categoriaService->crear(['nombre' => 'Periféricos', 'descripcion' => 'Teclados, ratones y auriculares']);
        $catComponentes = $categoriaService->crear(['nombre' => 'Componentes', 'descripcion' => 'Procesadores, RAM, placas y tarjetas de video']);
        $catAccesorios = $categoriaService->crear(['nombre' => 'Accesorios', 'descripcion' => 'Cables, hubs, soportes y mochilas']);

        // 2. 6 Marcas
        $marcaAsus = $marcaService->crear(['nombre' => 'ASUS']);
        $marcaLenovo = $marcaService->crear(['nombre' => 'Lenovo']);
        $marcaSamsung = $marcaService->crear(['nombre' => 'Samsung']);
        $marcaLogitech = $marcaService->crear(['nombre' => 'Logitech']);
        $marcaKingston = $marcaService->crear(['nombre' => 'Kingston']);
        $marcaCorsair = $marcaService->crear(['nombre' => 'Corsair']);

        // 3. 3 Proveedores
        $prov1 = $proveedorService->crear([
            'tipo_documento' => TipoDocumento::RUC,
            'numero_documento' => '20501234561',
            'razon_social' => 'Deltron Importaciones SAC',
            'contacto' => 'Carlos Mendoza',
            'telefono' => '987654321',
            'email' => 'ventas@deltron.pe',
            'direccion' => 'Av. Argentina 2020, Lima',
        ]);

        $prov2 = $proveedorService->crear([
            'tipo_documento' => TipoDocumento::RUC,
            'numero_documento' => '20609876542',
            'razon_social' => 'Ingram Micro Perú SAC',
            'contacto' => 'Ana Torres',
            'telefono' => '998877665',
            'email' => 'ventas@ingram.pe',
            'direccion' => 'Av. Javier Prado Este 440, San Isidro',
        ]);

        $prov3 = $proveedorService->crear([
            'tipo_documento' => TipoDocumento::RUC,
            'numero_documento' => '20451239873',
            'razon_social' => 'Tech Data del Perú EIRL',
            'contacto' => 'Jorge Ramos',
            'telefono' => '912345678',
            'email' => 'contacto@techdata.pe',
            'direccion' => 'Av. Wilson 1150, Lima',
        ]);

        // 4. 15 Productos
        $p1 = $productoService->crear([
            'sku' => 'LAP-ASUS-G16',
            'nombre' => 'Laptop ASUS ROG Strix G16',
            'categoria_id' => $catLaptops->id,
            'marca_id' => $marcaAsus->id,
            'modelo' => 'G614JVR',
            'precio_compra_referencial' => 5200.00,
            'precio_venta' => 6499.00,
            'controla_series' => true,
            'stock_minimo' => 2,
            'especificaciones' => ['Procesador' => 'Intel Core i9-14900HX', 'RAM' => '32GB DDR5', 'GPU' => 'RTX 4070 8GB'],
        ]);

        $p2 = $productoService->crear([
            'sku' => 'LAP-LEN-T14',
            'nombre' => 'Laptop Lenovo ThinkPad T14 Gen 4',
            'categoria_id' => $catLaptops->id,
            'marca_id' => $marcaLenovo->id,
            'modelo' => '21HD001JLM',
            'precio_compra_referencial' => 3800.00,
            'precio_venta' => 4899.00,
            'controla_series' => true,
            'stock_minimo' => 2,
            'especificaciones' => ['Procesador' => 'AMD Ryzen 7 PRO 7840U', 'RAM' => '16GB LPDDR5X', 'SSD' => '512GB NVMe'],
        ]);

        $p3 = $productoService->crear([
            'sku' => 'LAP-LEN-LEG5',
            'nombre' => 'Laptop Lenovo Legion 5 Pro',
            'categoria_id' => $catLaptops->id,
            'marca_id' => $marcaLenovo->id,
            'modelo' => '16IRX8',
            'precio_compra_referencial' => 4500.00,
            'precio_venta' => 5699.00,
            'controla_series' => true,
            'stock_minimo' => 1,
            'especificaciones' => ['Procesador' => 'Intel Core i7-13700HX', 'RAM' => '16GB DDR5', 'GPU' => 'RTX 4060 8GB'],
        ]);

        $p4 = $productoService->crear([
            'sku' => 'MON-SAM-G7',
            'nombre' => 'Monitor Samsung Odyssey G7 28" 4K',
            'categoria_id' => $catMonitores->id,
            'marca_id' => $marcaSamsung->id,
            'modelo' => 'LS28BG700ELXZX',
            'precio_compra_referencial' => 1800.00,
            'precio_venta' => 2399.00,
            'controla_series' => true,
            'stock_minimo' => 2,
            'especificaciones' => ['Resolución' => '3840 x 2160 UHD', 'Tasa' => '144Hz', 'Panel' => 'IPS 1ms'],
        ]);

        $p5 = $productoService->crear([
            'sku' => 'MON-ASUS-VG27',
            'nombre' => 'Monitor ASUS TUF Gaming VG27AQ 27"',
            'categoria_id' => $catMonitores->id,
            'marca_id' => $marcaAsus->id,
            'modelo' => 'VG27AQ',
            'precio_compra_referencial' => 950.00,
            'precio_venta' => 1299.00,
            'controla_series' => true,
            'stock_minimo' => 2,
            'especificaciones' => ['Resolución' => '2560 x 1440 WQHD', 'Tasa' => '165Hz', 'Panel' => 'IPS'],
        ]);

        $p6 = $productoService->crear([
            'sku' => 'PER-LOG-G502',
            'nombre' => 'Mouse Logitech G502 HERO High Performance',
            'categoria_id' => $catPerifericos->id,
            'marca_id' => $marcaLogitech->id,
            'modelo' => '910-005469',
            'precio_compra_referencial' => 130.00,
            'precio_venta' => 199.00,
            'controla_series' => false,
            'stock_minimo' => 5,
            'especificaciones' => ['Sensor' => 'HERO 25K', 'DPI' => '100 - 25,600', 'Botones' => '11 programables'],
        ]);

        $p7 = $productoService->crear([
            'sku' => 'PER-LOG-PROX',
            'nombre' => 'Teclado Mecánico Logitech G PRO X TKL',
            'categoria_id' => $catPerifericos->id,
            'marca_id' => $marcaLogitech->id,
            'modelo' => '920-012135',
            'precio_compra_referencial' => 450.00,
            'precio_venta' => 649.00,
            'controla_series' => true,
            'stock_minimo' => 2,
            'especificaciones' => ['Switches' => 'GX Brown Tactile', 'Conexión' => 'LIGHTSPEED / Bluetooth / USB'],
        ]);

        $p8 = $productoService->crear([
            'sku' => 'PER-COR-HS80',
            'nombre' => 'Auriculares Corsair HS80 RGB Wireless',
            'categoria_id' => $catPerifericos->id,
            'marca_id' => $marcaCorsair->id,
            'modelo' => 'CA-9011235-NA',
            'precio_compra_referencial' => 380.00,
            'precio_venta' => 529.00,
            'controla_series' => true,
            'stock_minimo' => 2,
            'especificaciones' => ['Audio' => 'Dolby Atmos Spatial Audio', 'Drivers' => 'Neodimio 50mm'],
        ]);

        $p9 = $productoService->crear([
            'sku' => 'COM-KIN-FURY16',
            'nombre' => 'Memoria RAM Kingston Fury Beast DDR5 16GB 5600MT/s',
            'categoria_id' => $catComponentes->id,
            'marca_id' => $marcaKingston->id,
            'modelo' => 'KF556C40BB-16',
            'precio_compra_referencial' => 180.00,
            'precio_venta' => 269.00,
            'controla_series' => false,
            'stock_minimo' => 8,
            'especificaciones' => ['Capacidad' => '16GB', 'Velocidad' => '5600 MT/s', 'Latencia' => 'CL40'],
        ]);

        $p10 = $productoService->crear([
            'sku' => 'COM-KIN-KC3000',
            'nombre' => 'Disco Sólido Kingston KC3000 M.2 NVMe 1TB PCIe 4.0',
            'categoria_id' => $catComponentes->id,
            'marca_id' => $marcaKingston->id,
            'modelo' => 'SKC3000S/1024G',
            'precio_compra_referencial' => 320.00,
            'precio_venta' => 449.00,
            'controla_series' => true,
            'stock_minimo' => 3,
            'especificaciones' => ['Lectura' => '7000 MB/s', 'Escritura' => '6000 MB/s'],
        ]);

        $p11 = $productoService->crear([
            'sku' => 'COM-SAM-990PRO',
            'nombre' => 'SSD Samsung 990 PRO 2TB PCIe 4.0 NVMe',
            'categoria_id' => $catComponentes->id,
            'marca_id' => $marcaSamsung->id,
            'modelo' => 'MZ-V9P2T0B/AM',
            'precio_compra_referencial' => 620.00,
            'precio_venta' => 849.00,
            'controla_series' => true,
            'stock_minimo' => 2,
            'especificaciones' => ['Lectura' => '7450 MB/s', 'Escritura' => '6900 MB/s'],
        ]);

        $p12 = $productoService->crear([
            'sku' => 'COM-COR-RM850X',
            'nombre' => 'Fuente de Poder Corsair RM850x 850W 80 Plus Gold',
            'categoria_id' => $catComponentes->id,
            'marca_id' => $marcaCorsair->id,
            'modelo' => 'CP-9020200-NA',
            'precio_compra_referencial' => 450.00,
            'precio_venta' => 619.00,
            'controla_series' => true,
            'stock_minimo' => 2,
            'especificaciones' => ['Certificación' => '80 PLUS Gold', 'Modular' => 'Totalmente modular'],
        ]);

        $p13 = $productoService->crear([
            'sku' => 'ACC-CAB-HDMI21',
            'nombre' => 'Cable HDMI 2.1 Ultra High Speed 8K 2m',
            'categoria_id' => $catAccesorios->id,
            'marca_id' => $marcaAsus->id,
            'modelo' => 'HDMI-8K-2M',
            'precio_compra_referencial' => 25.00,
            'precio_venta' => 49.00,
            'controla_series' => false,
            'stock_minimo' => 10,
            'especificaciones' => ['Ancho de banda' => '48 Gbps', 'Soporte' => '8K@60Hz / 4K@120Hz HDR'],
        ]);

        $p14 = $productoService->crear([
            'sku' => 'ACC-HUB-USBC',
            'nombre' => 'Hub USB-C 7 en 1 Multifuncional HDMI 4K',
            'categoria_id' => $catAccesorios->id,
            'marca_id' => $marcaKingston->id,
            'modelo' => 'HUB-C-7IN1',
            'precio_compra_referencial' => 70.00,
            'precio_venta' => 119.00,
            'controla_series' => false,
            'stock_minimo' => 5,
            'especificaciones' => ['Puertos' => 'HDMI 4K, 2x USB 3.0, SD, MicroSD, USB-C PD 100W'],
        ]);

        $p15 = $productoService->crear([
            'sku' => 'ACC-MOC-LEN15',
            'nombre' => 'Mochila para Laptop Lenovo Legion 16" Armor',
            'categoria_id' => $catAccesorios->id,
            'marca_id' => $marcaLenovo->id,
            'modelo' => 'GX40V10007',
            'precio_compra_referencial' => 110.00,
            'precio_venta' => 179.00,
            'controla_series' => false,
            'stock_minimo' => 4,
            'especificaciones' => ['Material' => 'Poliéster impermeable de alta resistencia', 'Compatibilidad' => 'Hasta 16 pulgadas'],
        ]);

        // 5. Compra 1 Recibida (Laptops y Monitores)
        $compra1 = $compraService->crearBorrador([
            'proveedor_id' => $prov1->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F001-0008892',
            'fecha_emision' => now()->subDays(5)->format('Y-m-d'),
            'moneda' => 'PEN',
            'observaciones' => 'Compra demo de lote de laptops y monitores gaming.',
        ], [
            ['producto_id' => $p1->id, 'cantidad' => 3, 'costo_unitario' => 5200.00],
            ['producto_id' => $p4->id, 'cantidad' => 2, 'costo_unitario' => 1800.00],
        ], $admin);

        $detallesC1 = $compra1->detalles->keyBy('producto_id');

        $inventarioService->recibirCompra($compra1, [
            $detallesC1[$p1->id]->id => [
                'ROG-G16-PE-2026-001',
                'ROG-G16-PE-2026-002',
                'ROG-G16-PE-2026-003',
            ],
            $detallesC1[$p4->id]->id => [
                'ODY-G7-4K-2026-001',
                'ODY-G7-4K-2026-002',
            ],
        ], $admin);

        // 6. Compra 2 Recibida (Componentes, Periféricos y Accesorios sin series)
        $compra2 = $compraService->crearBorrador([
            'proveedor_id' => $prov2->id,
            'tipo_comprobante' => TipoComprobante::FACTURA,
            'numero_comprobante' => 'F002-0001452',
            'fecha_emision' => now()->subDays(2)->format('Y-m-d'),
            'moneda' => 'PEN',
            'observaciones' => 'Compra demo de memorias, discos sólidos y periféricos.',
        ], [
            ['producto_id' => $p10->id, 'cantidad' => 2, 'costo_unitario' => 320.00],
            ['producto_id' => $p6->id, 'cantidad' => 10, 'costo_unitario' => 130.00],
            ['producto_id' => $p13->id, 'cantidad' => 20, 'costo_unitario' => 25.00],
        ], $admin);

        $detallesC2 = $compra2->detalles->keyBy('producto_id');

        $inventarioService->recibirCompra($compra2, [
            $detallesC2[$p10->id]->id => [
                'KC3000-NVME-1TB-001',
                'KC3000-NVME-1TB-002',
            ],
            // Los productos sin control de series no requieren array de series
        ], $admin);
    }
}
