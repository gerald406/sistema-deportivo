<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'sports.manage',
            'venues.manage',
            'categories.manage',
            'editions.manage',
            'seasons.manage',
            'teams.manage',
            'players.manage',
            'matches.manage',
            'matches.reopen',
            'reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions($permissions); // el admin recibe todos los permisos

        $organizador = Role::findOrCreate('organizador', 'web');
        // teams.manage: el organizador crea las delegaciones ("Flujo de
        // permisos" punto 3 de CLAUDE.md).
        // categories.manage: define los niveles de su olimpiada. En una BD
        // ya sembrada lo aplica la migracion
        // adjust_role_permissions_for_delegations.
        $organizador->syncPermissions([
            'editions.manage',
            'seasons.manage',
            'teams.manage',
            'venues.manage',
            'categories.manage',
            'matches.manage',
            // matches.reopen: sin el, SeasonPolicy::reopenMatches nunca deja
            // al organizador reabrir resultados de SU olimpiada (solo al admin).
            // Agregado en el fix de reaperturas; en la BD actual se aplico
            // con givePermissionTo().
            'matches.reopen',
            'reports.view',
        ]);

        $delegado = Role::findOrCreate('delegado', 'web');
        // Sin teams.manage: el delegado no edita su delegacion, solo
        // registra participantes (docs/fases/00-especificacion.md, D4).
        $delegado->syncPermissions([
            'players.manage',
            'reports.view',
        ]);
    }
}
