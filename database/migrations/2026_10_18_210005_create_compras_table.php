<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // COM-000001
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->string('tipo_comprobante'); // factura, boleta, guia, otro
            $table->string('numero_comprobante');
            $table->date('fecha_emision');
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 8, 4)->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('igv', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('estado')->default('borrador'); // borrador, recibida, anulada
            $table->text('observaciones')->nullable();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recibido_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['proveedor_id', 'tipo_comprobante', 'numero_comprobante']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
