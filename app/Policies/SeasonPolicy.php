<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Season;
use App\Models\User;

class SeasonPolicy
{
    /**
     * Una Season pertenece a un Tournament, que a su vez tiene un
     * organizer_id. El delegado no administra seasons, pero necesita
     * verlas para inscribir a su equipo (season_team), por eso view()
     * es mas permisivo que update()/delete().
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    public function view(User $user, Season $season): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('seasons.manage');
    }

    public function update(User $user, Season $season): bool
    {
        return $user->hasRole('organizador')
            && $user->can('seasons.manage')
            && $season->tournament->organizer_id === $user->id;
    }

    public function delete(User $user, Season $season): bool
    {
        return $user->hasRole('organizador')
            && $user->can('seasons.manage')
            && $season->tournament->organizer_id === $user->id;
    }

    /**
     * Accion especifica del negocio (no un CRUD generico): reabrir un
     * partido cerrado de la temporada. Se deja aqui porque la decision
     * de "quien puede reabrir" es a nivel de temporada/torneo, no de
     * partido individual.
     */
    public function reopenMatches(User $user, Season $season): bool
    {
        return $user->hasRole('organizador')
            && $user->can('matches.reopen')
            && $season->tournament->organizer_id === $user->id;
    }
}
