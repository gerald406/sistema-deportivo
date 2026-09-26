<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Bootstrap del primer administrador. Como solo el admin crea
     * cuentas (decision de negocio confirmada), el sistema necesita
     * nacer con al menos una cuenta admin para poder crear al resto
     * desde la interfaz.
     *
     * IMPORTANTE: cambiar esta contrasena inmediatamente despues del
     * primer login en un entorno real.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@sistema-deportivo.test'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('CambiaEstaClave123!'),
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
