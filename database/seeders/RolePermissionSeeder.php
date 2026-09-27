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
            'tournaments.manage',
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
        // teams.manage: el organizador crea equipos y les asigna delegado
        // ("Flujo de permisos" punto 3 de CLAUDE.md). Agregado en el fix de
        // permisos; en una BD ya sembrada se aplico con givePermissionTo().
        $organizador->syncPermissions([
            'tournaments.manage',
            'seasons.manage',
            'teams.manage',
            'venues.manage',
            'matches.manage',
            'reports.view',
        ]);

        $delegado = Role::findOrCreate('delegado', 'web');
        $delegado->syncPermissions([
            'teams.manage',
            'players.manage',
            'reports.view',
        ]);
    }
}
