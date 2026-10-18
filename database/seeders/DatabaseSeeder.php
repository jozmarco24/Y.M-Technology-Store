<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $rolAdmin = Role::firstOrCreate(['name' => 'admin']);
        $rolVendedor = Role::firstOrCreate(['name' => 'vendedor']);
        $rolAlmacen = Role::firstOrCreate(['name' => 'almacen']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@tienda.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole($rolAdmin);

        $vendedor = User::firstOrCreate(
            ['email' => 'vendedor@tienda.com'],
            [
                'name' => 'Vendedor',
                'password' => Hash::make('password'),
            ]
        );
        $vendedor->assignRole($rolVendedor);

        $almacen = User::firstOrCreate(
            ['email' => 'almacen@tienda.com'],
            [
                'name' => 'Almacenero',
                'password' => Hash::make('password'),
            ]
        );
        $almacen->assignRole($rolAlmacen);
    }
}
