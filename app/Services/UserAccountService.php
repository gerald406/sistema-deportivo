<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Gestion completa de cuentas de usuario, exclusiva de admin (regla de
 * negocio confirmada: "solo el admin crea cuentas"). La verificacion de
 * ese rol vive en el Livewire component (mount()/metodos), igual que en
 * DelegateAccountService: aqui se asume que el actor ya fue autorizado.
 */
class UserAccountService
{
    /**
     * @param array{name: string, email: string, password: string} $data
     * @param array<int, string> $roles
     */
    public function register(array $data, array $roles): User
    {
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
    public function update(User $user, array $data, array $roles): User
    {
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
     *   - no borrar un usuario que es organizer_id de algun Tournament
     *     o delegate_id de algun Team (historial real, no una FK RESTRICT
     *     generica: se revisa explicitamente porque users no tiene una
     *     unica relacion inversa obvia).
     */
    public function delete(User $user): bool|string
    {
        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return 'No se puede eliminar: es el único administrador del sistema.';
        }

        if (Tournament::where('organizer_id', $user->id)->exists()) {
            return 'No se puede eliminar: el usuario organiza torneos existentes.';
        }

        if (Team::where('delegate_id', $user->id)->exists()) {
            return 'No se puede eliminar: el usuario es delegado de uno o más equipos.';
        }

        $user->delete();

        return true;
    }
}
