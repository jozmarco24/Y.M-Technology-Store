<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TipoComprobanteVenta;
use App\Enums\TipoDocumentoCliente;
use App\Models\Cliente;
use App\Models\SerieComprobante;
use Illuminate\Database\Seeder;

class SeriesComprobantesYClientesSeeder extends Seeder
{
    public function run(): void
    {
        // Series de comprobantes por defecto: B001, F001, NV01
        SerieComprobante::firstOrCreate(
            [
                'tipo_comprobante' => TipoComprobanteVenta::BOLETA,
                'serie' => 'B001',
            ],
            [
                'correlativo_actual' => 0,
                'activo' => true,
            ]
        );

        SerieComprobante::firstOrCreate(
            [
                'tipo_comprobante' => TipoComprobanteVenta::FACTURA,
                'serie' => 'F001',
            ],
            [
                'correlativo_actual' => 0,
                'activo' => true,
            ]
        );

        SerieComprobante::firstOrCreate(
            [
                'tipo_comprobante' => TipoComprobanteVenta::NOTA_VENTA,
                'serie' => 'NV01',
            ],
            [
                'correlativo_actual' => 0,
                'activo' => true,
            ]
        );

        // Series para Notas de Crédito Electrónicas
        SerieComprobante::firstOrCreate(
            [
                'tipo_comprobante' => 'nota_credito',
                'serie' => 'FC01',
            ],
            [
                'correlativo_actual' => 0,
                'activo' => true,
            ]
        );

        SerieComprobante::firstOrCreate(
            [
                'tipo_comprobante' => 'nota_credito',
                'serie' => 'BC01',
            ],
            [
                'correlativo_actual' => 0,
                'activo' => true,
            ]
        );

        // Cliente genérico CLIENTES VARIOS (SIN_DOC)
        Cliente::firstOrCreate(
            [
                'tipo_documento' => TipoDocumentoCliente::SIN_DOC,
                'numero_documento' => null,
            ],
            [
                'nombre_razon_social' => 'CLIENTES VARIOS',
                'direccion' => 'Ciudad',
                'activo' => true,
            ]
        );
    }
}
