<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MatchEvent;
use App\Models\User;
use App\Support\SeasonAccess;

class MatchEventPolicy
{
    /**
     * Incidencias (goles, tarjetas...): permiso matches.manage; el
     * organizador solo en partidos de sus torneos.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('matches.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('matches.manage');
    }

    public function update(User $user, MatchEvent $event): bool
    {
        return SeasonAccess::manages($user, $event->match->season);
    }

    public function delete(User $user, MatchEvent $event): bool
    {
        return SeasonAccess::manages($user, $event->match->season);
    }
}
