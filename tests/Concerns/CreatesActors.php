<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Usuarios de prueba con los tres roles del sistema. Los roles y permisos
 * salen de RolePermissionSeeder (la misma matriz que produccion), nunca de
 * una copia escrita en la prueba.
 */
trait CreatesActors
{
    protected function admin(array $attributes = []): User
    {
        return $this->actor('admin', $attributes);
    }

    protected function organizer(array $attributes = []): User
    {
        return $this->actor('organizador', $attributes);
    }

    protected function delegate(array $attributes = []): User
    {
        return $this->actor('delegado', $attributes);
    }

    protected function seedRoles(): void
    {
        if (! Role::where('name', 'admin')->exists()) {
            $this->seed(RolePermissionSeeder::class);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function actor(string $role, array $attributes): User
    {
        $this->seedRoles();

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
