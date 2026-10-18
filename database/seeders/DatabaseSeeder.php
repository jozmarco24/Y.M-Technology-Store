<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesYPermisosSeeder::class);
        $this->call(SeriesComprobantesYClientesSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@tienda.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
            ]
        );
        $admin->syncRoles(['admin']);

        $vendedor = User::firstOrCreate(
            ['email' => 'vendedor@tienda.com'],
            [
                'name' => 'Vendedor',
                'password' => Hash::make('password'),
            ]
        );
        $vendedor->syncRoles(['vendedor']);

        $almacen = User::firstOrCreate(
            ['email' => 'almacen@tienda.com'],
            [
                'name' => 'Almacenero',
                'password' => Hash::make('password'),
            ]
        );
        $almacen->syncRoles(['almacen']);
    }
}
