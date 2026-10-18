<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PoliticaGarantia;
use Illuminate\Database\Seeder;

class PoliticaGarantiaSeeder extends Seeder
{
    public function run(): void
    {
        PoliticaGarantia::firstOrCreate(
            ['categoria_id' => null, 'producto_id' => null],
            [
                'nombre' => 'Garantía Estándar de Tienda (General)',
                'meses_garantia' => 12,
                'dias_cambio' => 7,
                'cubre' => "1. Defectos de fabricación en hardware y componentes internos.\n2. Fallas operativas espontáneas sin manipulación externa indebida.\n3. Reparación o reemplazo por servicio técnico certificado.",
                'no_cubre' => "1. Daño físico, golpes, quiñes, humedad o derrames de líquidos.\n2. Sellos de garantía o números de serie rotos, alterados o ilegibles.\n3. Fluctuaciones eléctricas o uso de cargadores no originales.\n4. Desgaste natural o software/virus.",
                'activo' => true,
            ]
        );
    }
}
