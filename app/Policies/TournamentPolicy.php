<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
{
    /**
     * El admin bypassa todas las policies via Gate::before en
     * AppServiceProvider, asi que aqui solo se resuelve el caso
     * "organizador dueno del torneo". El delegado no gestiona torneos.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    public function view(User $user, Tournament $tournament): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('tournaments.manage');
    }

    public function update(User $user, Tournament $tournament): bool
    {
        return $user->hasRole('organizador')
            && $user->can('tournaments.manage')
            && $tournament->organizer_id === $user->id;
    }

    public function delete(User $user, Tournament $tournament): bool
    {
        return $user->hasRole('organizador')
            && $user->can('tournaments.manage')
            && $tournament->organizer_id === $user->id;
    }
}
