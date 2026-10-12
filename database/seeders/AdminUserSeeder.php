<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Bootstrap del primer administrador. Como solo el admin crea
     * cuentas (decision de negocio confirmada), el sistema necesita
     * nacer con al menos una cuenta admin para poder crear al resto
     * desde la interfaz.
     *
     * Las credenciales salen de .env (ADMIN_NAME, ADMIN_EMAIL,
     * ADMIN_PASSWORD, via config/admin-user.php). Fuera de local son
     * obligatorias: no se siembra una clave conocida en produccion.
     * Si la cuenta ya existe no se toca (no pisa una clave ya cambiada).
     */
    public function run(): void
    {
        $email = config('admin-user.email');
        $password = config('admin-user.password');

        if (! $email || ! $password) {
            if (! app()->environment('local', 'testing')) {
                throw new RuntimeException('Define ADMIN_EMAIL y ADMIN_PASSWORD en .env antes de ejecutar el seeder.');
            }

            $email = 'admin@sistema-deportivo.test';
            $password = 'CambiaEstaClave123!';
        }

        if (mb_strlen($password) < 8) {
            throw new RuntimeException('ADMIN_PASSWORD debe tener al menos 8 caracteres.');
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => config('admin-user.name'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
