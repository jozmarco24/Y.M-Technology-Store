<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Clientes Web (autenticación guard 'cliente')
        Schema::create('clientes_web', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('nombre');
            $table->string('email')->unique();
            $table->string('telefono', 20)->nullable();
            $table->string('password');
            $table->timestamp('email_verificado_en')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Direcciones de Clientes Web
        Schema::create('direcciones_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_web_id')->constrained('clientes_web')->cascadeOnDelete();
            $table->string('alias', 50); // Ej. Casa, Oficina
            $table->string('destinatario');
            $table->string('telefono', 20);
            $table->string('departamento', 50);
            $table->string('provincia', 50);
            $table->string('distrito', 50);
            $table->string('direccion');
            $table->string('referencia')->nullable();
            $table->boolean('es_principal')->default(false);
            $table->timestamps();
        });

        // 3. Zonas de Envío
        Schema::create('zonas_envio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('departamento', 50)->nullable();
            $table->string('provincia', 50)->nullable();
            $table->string('distrito', 50)->nullable();
            $table->decimal('costo', 12, 2)->default(0.00);
            $table->integer('dias_estimados')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 4. Carritos y Carrito Ítems
        Schema::create('carritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_web_id')->nullable()->constrained('clientes_web')->nullOnDelete();
            $table->uuid('token')->unique();
            $table->timestamp('expira_en')->nullable();
            $table->timestamps();
        });

        Schema::create('carrito_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrito_id')->constrained('carritos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->integer('cantidad')->default(1);
            $table->decimal('precio_unitario', 12, 2);
            $table->timestamps();
        });

        // 5. Cupones de descuento
        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('tipo'); // TipoCupon: porcentaje, monto
            $table->decimal('valor', 12, 2);
            $table->decimal('minimo_compra', 12, 2)->nullable();
            $table->integer('usos_maximos')->nullable();
            $table->integer('usos_actuales')->default(0);
            $table->dateTime('desde')->nullable();
            $table->dateTime('hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 6. Pedidos Web
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // PED-000001
            $table->foreignId('cliente_web_id')->nullable()->constrained('clientes_web')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('estado'); // EstadoPedido
            $table->string('metodo_entrega'); // MetodoEntrega
            $table->jsonb('direccion_snapshot')->nullable();
            $table->foreignId('zona_envio_id')->nullable()->constrained('zonas_envio')->nullOnDelete();
            $table->foreignId('cupon_id')->nullable()->constrained('cupones')->nullOnDelete();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('costo_envio', 12, 2)->default(0.00);
            $table->decimal('descuento', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2)->default(0.00);
            $table->string('tipo_comprobante'); // boleta / factura
            $table->string('documento_tipo', 20); // DNI / RUC / CE
            $table->string('documento_numero', 20);
            $table->string('razon_social')->nullable();
            $table->string('direccion_fiscal')->nullable();
            $table->string('email_contacto');
            $table->string('telefono_contacto', 20)->nullable();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->timestamp('reserva_expira_en')->nullable()->index();
            $table->text('cancelado_motivo')->nullable();
            $table->string('tracking_codigo')->nullable();
            $table->string('courier')->nullable();
            $table->text('notas_cliente')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Pedido Ítems y Series reservadas
        Schema::create('pedido_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('descripcion');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        Schema::create('pedido_item_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_item_id')->constrained('pedido_items')->cascadeOnDelete();
            $table->foreignId('serie_id')->constrained('series')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['pedido_item_id', 'serie_id']);
        });

        // 8. Pagos Online
        Schema::create('pagos_online', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('pasarela'); // Pasarela
            $table->string('referencia_externa')->nullable()->index();
            $table->string('estado'); // EstadoPagoOnline
            $table->decimal('monto', 12, 2);
            $table->string('moneda', 3)->default('PEN');
            $table->jsonb('respuesta_json')->nullable();
            $table->integer('intentos')->default(0);
            $table->timestamp('pagado_en')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });

        // 9. Pedido Eventos (Solo inserción)
        Schema::create('pedido_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');
            $table->text('comentario')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 10. Webhooks recibidos
        Schema::create('webhooks_recibidos', function (Blueprint $table) {
            $table->id();
            $table->string('pasarela');
            $table->string('evento_id');
            $table->jsonb('payload');
            $table->boolean('procesado')->default(false);
            $table->timestamp('procesado_en')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['pasarela', 'evento_id']);
        });

        // 11. Ajustes a tablas existentes: productos, categorias, ventas y producto_imagenes
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('publicado_web')->default(false)->index();
            $table->boolean('destacado')->default(false)->index();
            $table->string('meta_titulo')->nullable();
            $table->text('meta_descripcion')->nullable();
            $table->decimal('peso_kg', 8, 2)->nullable();
        });

        Schema::table('categorias', function (Blueprint $table) {
            $table->integer('orden')->default(0)->index();
            $table->string('imagen')->nullable();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->string('canal', 20)->default('pos')->index(); // pos / web
        });

        Schema::create('producto_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('ruta');
            $table->integer('orden')->default(0);
            $table->boolean('es_principal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_imagenes');

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('canal');
        });

        Schema::table('categorias', function (Blueprint $table) {
            $table->dropColumn(['orden', 'imagen']);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['publicado_web', 'destacado', 'meta_titulo', 'meta_descripcion', 'peso_kg']);
        });

        Schema::dropIfExists('webhooks_recibidos');
        Schema::dropIfExists('pedido_eventos');
        Schema::dropIfExists('pagos_online');
        Schema::dropIfExists('pedido_item_series');
        Schema::dropIfExists('pedido_items');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('cupones');
        Schema::dropIfExists('carrito_items');
        Schema::dropIfExists('carritos');
        Schema::dropIfExists('zonas_envio');
        Schema::dropIfExists('direcciones_cliente');
        Schema::dropIfExists('clientes_web');
    }
};
