<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Matchday;
use App\Models\User;
use App\Support\SeasonAccess;

class MatchdayPolicy
{
    /**
     * Jornadas: permiso matches.manage (admin y organizador). El
     * organizador solo gestiona las de temporadas de sus torneos.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('matches.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('matches.manage');
    }

    public function update(User $user, Matchday $matchday): bool
    {
        return SeasonAccess::manages($user, $matchday->season);
    }

    public function delete(User $user, Matchday $matchday): bool
    {
        return SeasonAccess::manages($user, $matchday->season);
    }
}
