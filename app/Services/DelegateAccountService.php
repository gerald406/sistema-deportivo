<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;

/**
 * Aparte de TeamRegistrationService a proposito: crear una cuenta de
 * usuario es un concern distinto (aprovisionamiento de cuentas), no
 * logica de Team. Mezclarlo dentro de TeamRegistrationService haria que
 * un servicio de dominio de equipos tambien supiera de Hash/roles/User.
 */
class DelegateAccountService
{
    /**
     * @param array{name: string, email: string, password: string} $data
     */
    public function createQuickAccount(array $data, User $actor): User
    {
        // Regla de negocio confirmada en Fase 2: SOLO el admin crea
        // cuentas. Se revalida aqui, en el Service, no solo ocultando el
        // boton en el Blade: un metodo publico de un componente Livewire
        // es invocable directamente (p. ej. desde la consola del
        // navegador) sin pasar por la vista.
        if (! $actor->hasRole('admin')) {
            throw new AuthorizationException('Solo un administrador puede crear cuentas de usuario.');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('delegado');

        return $user;
    }
}
