<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_comprobantes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_comprobante'); // boleta, factura, nota_venta
            $table->string('serie'); // B001, F001, NV01
            $table->integer('correlativo_actual')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tipo_comprobante', 'serie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_comprobantes');
    }
};
