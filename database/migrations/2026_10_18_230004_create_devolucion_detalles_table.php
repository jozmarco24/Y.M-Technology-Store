<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolucion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('venta_detalle_id')->constrained('venta_detalles')->restrictOnDelete();
            $table->foreignId('serie_id')->nullable()->constrained('series')->nullOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->integer('cantidad')->default(1);
            $table->decimal('monto_devuelto', 12, 2);
            $table->string('estado_fisico'); // nuevo_sellado / abierto_ok / danado / con_defecto
            $table->string('destino_serie'); // disponible / en_garantia / baja
            $table->timestamps();

            $table->index(['devolucion_id', 'serie_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_detalles');
    }
};
