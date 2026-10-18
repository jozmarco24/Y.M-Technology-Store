<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Lista de permisos del sistema
        $permisos = [
            // Productos
            'productos.ver',
            'productos.crear',
            'productos.editar',
            // Categorias
            'categorias.ver',
            'categorias.crear',
            'categorias.editar',
            'categorias.eliminar',
            // Marcas
            'marcas.ver',
            'marcas.crear',
            'marcas.editar',
            'marcas.eliminar',
            // Proveedores
            'proveedores.ver',
            'proveedores.crear',
            'proveedores.editar',
            'proveedores.eliminar',
            // Compras
            'compras.ver',
            'compras.crear',
            'compras.recibir',
            'compras.anular',
            // Series
            'series.ver',
            'series.dar_baja',
            'series.ajustar_estado',
            // Movimientos
            'movimientos.ver',
            // Costos y márgenes
            'costos.ver',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $almacen = Role::firstOrCreate(['name' => 'almacen']);
        $vendedor = Role::firstOrCreate(['name' => 'vendedor']);

        // Admin tiene todos los permisos
        $admin->syncPermissions(Permission::all());

        // Almacén
        $almacen->syncPermissions([
            'productos.ver',
            'productos.crear',
            'productos.editar',
            'categorias.ver',
            'categorias.crear',
            'categorias.editar',
            'categorias.eliminar',
            'marcas.ver',
            'marcas.crear',
            'marcas.editar',
            'marcas.eliminar',
            'proveedores.ver',
            'proveedores.crear',
            'proveedores.editar',
            'proveedores.eliminar',
            'compras.ver',
            'compras.crear',
            'compras.recibir',
            // NO compras.anular
            'series.ver',
            'series.dar_baja',
            // NO series.ajustar_estado
            'movimientos.ver',
            // NO costos.ver
        ]);

        // Vendedor
        $vendedor->syncPermissions([
            'productos.ver',
            'series.ver',
            // Sin acceso a compras, movimientos, costos, ni crear/editar
        ]);
    }
}
