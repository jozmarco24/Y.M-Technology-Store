<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // VEN-000001
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('vendedor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('caja_sesion_id')->constrained('cajas_sesiones')->restrictOnDelete();
            $table->string('tipo_comprobante'); // boleta, factura, nota_venta
            $table->string('serie_comprobante');
            $table->integer('numero_comprobante');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('igv', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('estado')->default('completada'); // completada, anulada
            $table->text('observaciones')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulada_en')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tipo_comprobante', 'serie_comprobante', 'numero_comprobante']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
