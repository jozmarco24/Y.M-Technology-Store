<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('compra_detalle_id')->nullable()->constrained('compra_detalles')->nullOnDelete();
            $table->string('numero_serie');
            $table->string('estado')->default('disponible'); // disponible, reservada, vendida, en_garantia, devuelta, baja
            $table->decimal('costo', 12, 2);
            $table->timestamp('fecha_ingreso');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['producto_id', 'numero_serie']);
            $table->index('estado');
            $table->index('numero_serie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
