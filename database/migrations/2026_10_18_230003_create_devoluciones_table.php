<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // DEV-000001
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('tipo'); // devolucion_dinero / cambio_producto / nota_credito
            $table->string('motivo'); // defecto_fabrica / arrepentimiento / error_venta / producto_incorrecto / otro
            $table->text('detalle_motivo');
            $table->string('estado')->default('solicitada'); // solicitada / aprobada / rechazada / completada
            $table->decimal('total_devuelto', 12, 2)->default(0);
            $table->string('metodo_reembolso')->nullable(); // efectivo / yape / plin / tarjeta / transferencia
            $table->foreignId('solicitado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('aprobado_en')->nullable();
            $table->dateTime('completada_en')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['venta_id', 'estado']);
            $table->index(['cliente_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones');
    }
};
