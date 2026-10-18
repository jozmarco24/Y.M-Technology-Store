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
        Schema::create('ordenes_servicio', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // OS-000001
            $table->string('tipo'); // garantia / pago (TipoOrdenServicio)
            $table->foreignId('serie_id')->nullable()->constrained('series')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('equipo_descripcion')->nullable(); // Para equipos que no se vendieron en la tienda
            $table->string('serie_externa')->nullable();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('garantia_id')->nullable()->constrained('garantias')->nullOnDelete();
            $table->text('falla_reportada');
            $table->text('estado_recepcion'); // golpes, accesorios entregados
            $table->string('estado'); // EstadoOrdenServicio
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('diagnostico')->nullable();
            $table->text('solucion')->nullable();
            $table->decimal('costo_estimado', 12, 2)->nullable();
            $table->decimal('costo_final', 12, 2)->nullable();
            $table->boolean('aprobado_por_cliente')->nullable();
            $table->timestamp('aprobado_en')->nullable();
            $table->date('fecha_promesa')->nullable();
            $table->foreignId('recibido_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('recibido_en');
            $table->timestamp('entregado_en')->nullable();
            $table->string('entregado_a')->nullable();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete(); // si se cobra
            $table->integer('garantia_reparacion_dias')->default(30);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('orden_servicio_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_servicio_id')->constrained('ordenes_servicio')->cascadeOnDelete();
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');
            $table->text('comentario')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('orden_servicio_repuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_servicio_id')->constrained('ordenes_servicio')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('descripcion');
            $table->integer('cantidad');
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('precio_unitario', 12, 2);
            $table->foreignId('serie_id')->nullable()->constrained('series')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('orden_servicio_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_servicio_id')->constrained('ordenes_servicio')->cascadeOnDelete();
            $table->string('ruta');
            $table->string('descripcion')->nullable();
            $table->string('momento'); // ingreso / diagnostico / salida (MomentoFoto)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_servicio_fotos');
        Schema::dropIfExists('orden_servicio_repuestos');
        Schema::dropIfExists('orden_servicio_eventos');
        Schema::dropIfExists('ordenes_servicio');
    }
};
