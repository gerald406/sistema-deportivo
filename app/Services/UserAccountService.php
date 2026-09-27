<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

/**
 * Gestion completa de cuentas de usuario, exclusiva de admin (regla de
 * negocio confirmada: "solo el admin crea cuentas"). Igual que en
 * DelegateAccountService, el rol se revalida aqui en el Service y no solo
 * en el mount() del componente: un metodo publico de un componente
 * Livewire es invocable directamente (p. ej. desde la consola del
 * navegador) sin pasar por la vista.
 */
class UserAccountService
{
    /**
     * @param array{name: string, email: string, password: string} $data
     * @param array<int, string> $roles
     */
    public function register(array $data, array $roles, User $actor): User
    {
        $this->ensureAdmin($actor);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->syncRoles($roles);

        return $user;
    }

    /**
     * @param array{name: string, email: string, password: string|null, is_active: bool} $data
     * @param array<int, string> $roles
     */
    public function update(User $user, array $data, array $roles, User $actor): User
    {
        $this->ensureAdmin($actor);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $data['is_active'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);
        $user->syncRoles($roles);

        return $user;
    }

    /**
     * Salvaguardas antes de borrar (sin soft deletes):
     *   - no dejar el sistema sin ningun admin.
     *   - FKs RESTRICT: organizer_id de Tournament, user_id de
     *     MatchReopenLog (auditoria).
     *   - delegate_id de Team / SeasonTeam: en la BD son SET NULL, pero
     *     se bloquea igual para no dejar equipos sin delegado en silencio;
     *     primero hay que reasignarlos.
     *   - players.created_by (SET NULL) si se permite: solo afecta la
     *     autorizacion de edicion, no el historial.
     */
    public function delete(User $user, User $actor): bool|string
    {
        $this->ensureAdmin($actor);

        // El boton ya se oculta en el Blade, pero delete() es invocable
        // directamente desde el navegador.
        if ($user->is($actor)) {
            return 'No puedes eliminar tu propia cuenta.';
        }

        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return 'No se puede eliminar: es el único administrador del sistema.';
        }

        if ($user->tournaments()->exists()) {
            return 'No se puede eliminar: el usuario organiza torneos existentes.';
        }

        if ($user->delegatedTeams()->exists() || $user->delegatedSeasonTeams()->exists()) {
            return 'No se puede eliminar: el usuario es delegado de uno o más equipos.';
        }

        if ($user->matchReopenLogs()->exists()) {
            return 'No se puede eliminar: el usuario tiene reaperturas de partidos registradas. Desactívalo en su lugar.';
        }

        try {
            $user->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: el usuario tiene historial asociado. Desactívalo en su lugar.';
        }

        return true;
    }

    private function ensureAdmin(User $actor): void
    {
        if (! $actor->hasRole('admin')) {
            throw new AuthorizationException('Solo un administrador puede gestionar cuentas de usuario.');
        }
    }
}
