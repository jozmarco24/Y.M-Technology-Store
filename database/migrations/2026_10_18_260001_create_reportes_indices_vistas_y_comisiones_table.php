<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Índices optimizados para reportes y agregaciones en PostgreSQL
        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['created_at', 'estado', 'canal'], 'idx_ventas_reportes_fecha_estado_canal');
            $table->index(['vendedor_id', 'estado', 'created_at'], 'idx_ventas_reportes_vendedor');
            $table->index(['cliente_id', 'estado', 'created_at'], 'idx_ventas_reportes_cliente');
        });

        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->index(['producto_id', 'venta_id'], 'idx_venta_detalles_prod_venta');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->index(['estado', 'producto_id'], 'idx_series_estado_producto');
            $table->index(['created_at', 'estado'], 'idx_series_antiguedad');
        });

        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->index(['producto_id', 'created_at'], 'idx_movimientos_prod_fecha');
        });

        Schema::table('compra_detalles', function (Blueprint $table) {
            $table->index(['producto_id', 'compra_id'], 'idx_compra_detalles_prod_compra');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->index(['proveedor_id', 'estado', 'created_at'], 'idx_compras_proveedor_reporte');
        });

        // 2. Tabla de reglas de comisión para vendedores (opcional, configurable detrás de flag)
        Schema::create('reglas_comision', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30); // 'porcentaje_venta' o 'porcentaje_margen'
            $table->decimal('porcentaje', 5, 2); // Ej: 3.50%
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->date('vigente_desde')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 3. Vistas SQL optimizadas para reportes
        // Vista: v_ventas_netas (Ventas completadas consolidando devoluciones completadas)
        DB::statement('DROP VIEW IF EXISTS v_ventas_netas');
        DB::statement("
            CREATE VIEW v_ventas_netas AS
            SELECT 
                v.id AS venta_id,
                v.codigo,
                v.cliente_id,
                v.vendedor_id,
                v.tipo_comprobante,
                v.serie_comprobante,
                v.numero_comprobante,
                v.canal,
                v.subtotal AS venta_subtotal,
                v.descuento AS venta_descuento,
                v.igv AS venta_igv,
                v.total AS venta_total,
                COALESCE(dev.total_devuelto, 0) AS total_devuelto,
                (v.total - COALESCE(dev.total_devuelto, 0)) AS total_neto,
                v.created_at
            FROM ventas v
            LEFT JOIN (
                SELECT 
                    venta_id, 
                    SUM(total_devuelto) AS total_devuelto
                FROM devoluciones
                WHERE estado = 'completada'
                GROUP BY venta_id
            ) dev ON dev.venta_id = v.id
            WHERE v.estado = 'completada'
        ");

        // Vista: v_ultima_venta_producto (Fecha y datos de la última venta por producto para cálculo de stock muerto)
        DB::statement('DROP VIEW IF EXISTS v_ultima_venta_producto');
        DB::statement("
            CREATE VIEW v_ultima_venta_producto AS
            SELECT 
                vd.producto_id,
                MAX(v.created_at) AS ultima_venta_en,
                COUNT(vd.id) AS total_veces_vendido,
                SUM(vd.cantidad) AS unidades_vendidas_historicas
            FROM venta_detalles vd
            INNER JOIN ventas v ON v.id = vd.venta_id
            WHERE v.estado = 'completada'
            GROUP BY vd.producto_id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_ultima_venta_producto');
        DB::statement('DROP VIEW IF EXISTS v_ventas_netas');

        Schema::dropIfExists('reglas_comision');

        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex('idx_compras_proveedor_reporte');
        });

        Schema::table('compra_detalles', function (Blueprint $table) {
            $table->dropIndex('idx_compra_detalles_prod_compra');
        });

        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropIndex('idx_movimientos_prod_fecha');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropIndex('idx_series_antiguedad');
            $table->dropIndex('idx_series_estado_producto');
        });

        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropIndex('idx_venta_detalles_prod_venta');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_reportes_cliente');
            $table->dropIndex('idx_ventas_reportes_vendedor');
            $table->dropIndex('idx_ventas_reportes_fecha_estado_canal');
        });
    }
};
