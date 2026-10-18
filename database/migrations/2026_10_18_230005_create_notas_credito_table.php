<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->string('serie'); // FC01, BC01
            $table->integer('numero');
            $table->string('motivo_sunat')->default('01'); // Catálogo 09 SUNAT (01 = Anulación de la operación, 07 = Devolución total, etc.)
            $table->decimal('monto', 12, 2);
            $table->string('estado')->default('pendiente'); // pendiente / aceptada / rechazada / error
            $table->foreignId('comprobante_electronico_id')->nullable()->constrained('comprobantes_electronicos')->nullOnDelete();
            $table->timestamps();

            $table->unique(['serie', 'numero']);
            $table->index(['venta_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_credito');
    }
};
