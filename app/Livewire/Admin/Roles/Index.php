<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use App\Livewire\Concerns\ExportsExcel;
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
    use ExportsExcel;

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

    protected function authorizeExport(): void
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
    }

    protected function exportTitle(): string
    {
        return 'Roles y permisos';
    }

    protected function exportRows(): iterable
    {
        return Permission::orderBy('name')->get();
    }

    /** Una fila por permiso y una columna por rol, con lo guardado en la base (no lo marcado sin guardar). */
    protected function exportColumns(): array
    {
        $columns = ['Permiso' => fn (Permission $p) => $p->name];

        foreach (Role::with('permissions:id,name')->orderBy('name')->get() as $role) {
            // El admin no tiene permisos asignados: pasa por Gate::before.
            $columns[ucfirst($role->name)] = fn (Permission $p) => $role->name === 'admin' || $role->permissions->contains('name', $p->name);
        }

        return $columns;
    }

    public function render()
    {
        return view('livewire.admin.roles.index', [
            'roles' => Role::orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(),
        ]);
    }
}
