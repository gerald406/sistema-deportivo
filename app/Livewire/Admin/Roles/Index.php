<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use App\Services\RolePermissionAdminService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin')]
#[Title('Roles y permisos')]
class Index extends Component
{
    private const EDITABLE_ROLES = ['organizador', 'delegado'];

    /** @var array<string, array<int, string>> nombre de rol => permisos seleccionados */
    public array $rolePermissions = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        foreach (Role::with('permissions')->get() as $role) {
            $this->rolePermissions[$role->name] = $role->permissions->pluck('name')->all();
        }
    }

    public function isEditable(string $roleName): bool
    {
        return in_array($roleName, self::EDITABLE_ROLES, true);
    }

    public function save(string $roleName, RolePermissionAdminService $service): void
    {
        abort_unless($this->isEditable($roleName), 403);

        $role = Role::where('name', $roleName)->firstOrFail();
        $service->updatePermissions($role, $this->rolePermissions[$roleName] ?? []);

        $this->dispatch('toast', type: 'success', message: "Permisos de '{$roleName}' actualizados.");
    }

    public function render()
    {
        return view('livewire.admin.roles.index', [
            'roles' => Role::orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(),
        ]);
    }
}
