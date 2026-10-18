<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_sesion_id')->constrained('cajas_sesiones')->restrictOnDelete();
            $table->string('tipo'); // ingreso, egreso
            $table->string('concepto');
            $table->decimal('monto', 12, 2);
            $table->string('metodo_pago')->nullable(); // efectivo, yape, etc.
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas_movimientos');
    }
};
