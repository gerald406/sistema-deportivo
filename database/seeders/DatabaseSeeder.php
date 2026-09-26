<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles/permisos y el admin bootstrap van primero: el resto de
        // seeders y cualquier prueba manual asume que ya existen.
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            SportSeeder::class,
            DisciplineSeeder::class,
            SportPositionSeeder::class,
            EventTypeSeeder::class,
            CategorySeeder::class,
        ]);
    }
}
