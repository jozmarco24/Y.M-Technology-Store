<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garantias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serie_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('venta_detalle_id')->constrained('venta_detalles')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('politica_garantia_id')->nullable()->constrained('politicas_garantia')->nullOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->date('dias_cambio_hasta');
            $table->string('estado')->default('vigente');
            $table->text('condiciones_snapshot')->nullable();
            $table->timestamps();

            $table->index(['serie_id', 'estado']);
            $table->index(['fecha_fin', 'estado']);
        });

        // Índice único parcial en PostgreSQL: solo puede existir una garantía vigente por serie
        DB::statement("CREATE UNIQUE INDEX garantias_serie_vigente_unique ON garantias (serie_id) WHERE estado = 'vigente'");
    }

    public function down(): void
    {
        Schema::dropIfExists('garantias');
    }
};
