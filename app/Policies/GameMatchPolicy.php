<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GameMatch;
use App\Models\User;
use App\Support\SeasonAccess;

class GameMatchPolicy
{
    /**
     * Partidos: permiso matches.manage (admin y organizador). El
     * organizador solo gestiona los de temporadas de sus torneos.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('matches.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('matches.manage');
    }

    public function update(User $user, GameMatch $match): bool
    {
        return SeasonAccess::manages($user, $match->season);
    }

    public function delete(User $user, GameMatch $match): bool
    {
        return SeasonAccess::manages($user, $match->season);
    }
}
