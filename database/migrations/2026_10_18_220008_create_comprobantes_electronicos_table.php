<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comprobantes_electronicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->string('tipo'); // boleta, factura, nota_credito
            $table->string('serie', 10);
            $table->unsignedInteger('numero');
            $table->string('estado')->default('pendiente'); // pendiente, enviando, aceptado, rechazado, error
            $table->jsonb('payload_json')->nullable();
            $table->jsonb('respuesta_json')->nullable();
            $table->text('enlace_pdf')->nullable();
            $table->text('enlace_xml')->nullable();
            $table->text('enlace_cdr')->nullable();
            $table->text('codigo_qr')->nullable();
            $table->string('hash', 255)->nullable();
            $table->string('codigo_sunat', 50)->nullable();
            $table->text('mensaje_sunat')->nullable();
            $table->unsignedInteger('intentos')->default(0);
            $table->timestamp('ultimo_intento_en')->nullable();
            $table->timestamp('aceptado_en')->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index(['tipo', 'serie', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comprobantes_electronicos');
    }
};
