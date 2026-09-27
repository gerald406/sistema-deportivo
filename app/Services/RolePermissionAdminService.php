<?php

declare(strict_types=1);

namespace App\Services;

use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Solo permite AJUSTAR los permisos de los roles ya existentes
 * (organizador, delegado). No crea ni elimina roles: la regla de negocio
 * confirmada es exactamente 3 roles fijos (admin/organizador/delegado).
 * El rol admin queda fuera a proposito: Gate::before le da bypass total
 * sin importar sus permisos sincronizados, asi que editarlo desde aqui
 * no cambiaria nada funcional y solo confundiria al operador.
 */
class RolePermissionAdminService
{
    private const EDITABLE_ROLES = ['organizador', 'delegado'];

    /**
     * @param array<int, string> $permissions
     */
    public function updatePermissions(Role $role, array $permissions): void
    {
        if (! in_array($role->name, self::EDITABLE_ROLES, true)) {
            throw new \InvalidArgumentException("El rol '{$role->name}' no es editable desde este panel.");
        }

        $role->syncPermissions($permissions);

        // Mecanismo oficial de invalidacion de Spatie (no un Cache::forget
        // manual, que podria no coincidir con el store/tags reales que usa
        // el paquete). Sin esto los cambios no se reflejan hasta que
        // expire el cache por su cuenta.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
