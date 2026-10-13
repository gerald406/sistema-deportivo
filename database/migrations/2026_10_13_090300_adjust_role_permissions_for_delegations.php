<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Ajuste de la matriz de permisos en una base ya sembrada (la misma
     * matriz esta en RolePermissionSeeder para instalaciones nuevas):
     *   - El delegado pierde teams.manage: ya no edita su delegacion, solo
     *     registra participantes (players.manage).
     *   - El organizador gana categories.manage: define los niveles de su
     *     olimpiada.
     */
    public function up(): void
    {
        $this->revoke('delegado', 'teams.manage');
        $this->grant('organizador', 'categories.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $this->grant('delegado', 'teams.manage');
        $this->revoke('organizador', 'categories.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grant(string $role, string $permission): void
    {
        [$roleId, $permissionId] = $this->ids($role, $permission);

        if ($roleId && $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    private function revoke(string $role, string $permission): void
    {
        [$roleId, $permissionId] = $this->ids($role, $permission);

        if ($roleId && $permissionId) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
        }
    }

    /** @return array{0: int|null, 1: int|null} */
    private function ids(string $role, string $permission): array
    {
        return [
            DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id'),
            DB::table('permissions')->where('name', $permission)->where('guard_name', 'web')->value('id'),
        ];
    }
};
