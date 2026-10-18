<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('marca_id')->constrained('marcas')->restrictOnDelete();
            $table->string('modelo')->nullable();
            $table->decimal('precio_compra_referencial', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2);
            $table->boolean('incluye_igv')->default(true);
            $table->boolean('controla_series')->default(true);
            $table->integer('stock_minimo')->default(0);
            $table->string('imagen')->nullable();
            $table->jsonb('especificaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
