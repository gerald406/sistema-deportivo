<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GamesEdition;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;

/**
 * Aparte de EditionTeamService a proposito: crear una cuenta de usuario es
 * un concern distinto (aprovisionamiento de cuentas), no logica de
 * delegaciones. Mezclarlo haria que un servicio de dominio tambien supiera
 * de Hash/roles/User.
 */
class DelegateAccountService
{
    /**
     * Crea una cuenta con el rol "delegado" y NINGUN otro: el rol no viene
     * de $data, asi que no se puede inyectar desde el navegador.
     *
     * Quien puede (docs/fases/00-especificacion.md, D3): el admin, y el
     * organizador que tiene al menos una olimpiada a su cargo. Las cuentas
     * de cualquier otro rol las crea solo el admin (UserAccountService).
     * Se revalida aqui, en el Service: un metodo publico de un componente
     * Livewire es invocable directamente sin pasar por la vista.
     *
     * @param array{name: string, email: string, password: string} $data
     */
    public function createQuickAccount(array $data, User $actor): User
    {
        if (! $this->canCreate($actor)) {
            throw new AuthorizationException('Solo el administrador o el organizador de una olimpiada pueden crear cuentas de delegado.');
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

    public function canCreate(User $actor): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        return $actor->hasRole('organizador')
            && $actor->can('editions.manage')
            && GamesEdition::where('organizer_id', $actor->id)->exists();
    }
}
