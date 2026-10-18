<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configs = [
            ['clave' => 'tienda_nombre', 'valor' => 'TIENDA DE TECNOLOGÍA & HARDWARE', 'descripcion' => 'Nombre comercial de la tienda'],
            ['clave' => 'tienda_ruc', 'valor' => '20601234568', 'descripcion' => 'RUC de la empresa emisora'],
            ['clave' => 'tienda_razon_social', 'valor' => 'SYS-COPM IMPORTADORA S.A.C.', 'descripcion' => 'Razón social legal'],
            ['clave' => 'tienda_direccion', 'valor' => 'Av. Garcilaso de la Vega 1348, Int. 204, Lima', 'descripcion' => 'Dirección fiscal y comercial'],
            ['clave' => 'tienda_telefono', 'valor' => '(01) 424-5566 / 998 877 665', 'descripcion' => 'Teléfonos de contacto'],
            ['clave' => 'tienda_email', 'valor' => 'ventas@tienda-syscopm.pe', 'descripcion' => 'Correo de ventas'],
            ['clave' => 'tienda_mensaje_pie', 'valor' => '¡Gracias por su compra! Revise su comprobante electrónico.', 'descripcion' => 'Mensaje al pie del ticket'],
        ];

        foreach ($configs as $config) {
            Configuracion::updateOrCreate(
                ['clave' => $config['clave']],
                [
                    'valor' => $config['valor'],
                    'descripcion' => $config['descripcion'],
                ]
            );
        }
    }
}
