<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cambios_serie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('serie_original_id')->constrained('series')->restrictOnDelete();
            $table->foreignId('serie_nueva_id')->constrained('series')->restrictOnDelete();
            $table->decimal('diferencia_monto', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['devolucion_id', 'serie_nueva_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_serie');
    }
};
