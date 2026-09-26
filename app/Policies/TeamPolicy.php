<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * El delegado gestiona su(s) propio(s) equipo(s) via delegate_id
     * (delegado general del club). La precedencia con el delegado de
     * season_team se resuelve en el contexto de Season/SeasonTeam, no
     * aqui: esta policy protege el recurso Team en si mismo.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    public function view(User $user, Team $team): bool
    {
        if ($user->hasAnyRole(['admin', 'organizador'])) {
            return true;
        }

        return $team->delegate_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('delegado') && $user->can('teams.manage');
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasRole('delegado')
            && $user->can('teams.manage')
            && $team->delegate_id === $user->id;
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->hasRole('delegado')
            && $user->can('teams.manage')
            && $team->delegate_id === $user->id;
    }
}
