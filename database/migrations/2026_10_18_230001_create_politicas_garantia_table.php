<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('politicas_garantia', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->integer('meses_garantia')->default(12);
            $table->integer('dias_cambio')->default(7);
            $table->text('cubre')->nullable();
            $table->text('no_cubre')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['categoria_id', 'producto_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politicas_garantia');
    }
};
