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
        Schema::create('cajas_sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('abierta_en');
            $table->timestamp('cerrada_en')->nullable();
            $table->decimal('monto_apertura', 12, 2);
            $table->decimal('monto_cierre_esperado', 12, 2)->nullable();
            $table->decimal('monto_cierre_real', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->nullable();
            $table->string('estado')->default('abierta'); // abierta, cerrada
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // Índice único parcial en PostgreSQL: solo UNA sesión abierta por usuario
        DB::statement("CREATE UNIQUE INDEX unique_caja_abierta_por_usuario ON cajas_sesiones (user_id) WHERE estado = 'abierta'");
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas_sesiones');
    }
};
