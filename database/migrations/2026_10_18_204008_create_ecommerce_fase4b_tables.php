<?php

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
        // 1. Banners administrables para la tienda web
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('subtitulo')->nullable();
            $table->string('imagen_url');
            $table->string('enlace')->nullable();
            $table->string('boton_texto')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 2. Páginas informativas y legales (términos, privacidad, políticas)
        Schema::create('paginas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->longText('contenido');
            $table->string('meta_titulo')->nullable();
            $table->text('meta_descripcion')->nullable();
            $table->boolean('publicada')->default(true);
            $table->timestamps();
        });

        // 3. Libro de Reclamaciones virtual (INDECOPI)
        Schema::create('reclamaciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // REC-000001
            $table->string('tipo'); // reclamo, queja
            $table->string('consumidor_nombre');
            $table->string('consumidor_tipo_documento'); // DNI, CE, RUC
            $table->string('consumidor_numero_documento');
            $table->string('consumidor_telefono');
            $table->string('consumidor_email');
            $table->string('consumidor_domicilio');
            $table->boolean('es_menor_edad')->default(false);
            $table->string('apoderado_nombre')->nullable();
            $table->string('apoderado_telefono')->nullable();
            $table->string('apoderado_email')->nullable();
            $table->string('bien_contratado'); // producto, servicio
            $table->decimal('monto_reclamado', 12, 2)->nullable();
            $table->text('descripcion_bien')->nullable();
            $table->text('detalle_reclamacion');
            $table->text('pedido_consumidor'); // Lo que solicita el consumidor
            $table->string('estado')->default('pendiente'); // pendiente, en_proceso, atendido, rechazado
            $table->text('respuesta_proveedor')->nullable();
            $table->timestamp('respondido_en')->nullable();
            $table->foreignId('respondido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reclamaciones');
        Schema::dropIfExists('paginas');
        Schema::dropIfExists('banners');
    }
};
