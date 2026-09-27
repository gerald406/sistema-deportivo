<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EventParticipant;
use App\Models\SeasonTeam;
use App\Models\User;
use App\Support\SeasonAccess;

class EventLineupPolicy
{
    /**
     * Orden de relevos de una posta: lo cargan el organizador del torneo,
     * el admin y el delegado EFECTIVO del equipo (mismo criterio que el
     * plantel, SeasonTeamPlayerPolicy).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('matches.manage') || ($user->hasRole('delegado') && $user->can('teams.manage'));
    }

    /**
     * Estatica: la usan el Service (al guardar) y el componente (listado).
     * $participant debe ser un season_team de una posta.
     */
    public static function managesParticipant(User $user, EventParticipant $participant): bool
    {
        $match = $participant->match;

        if (SeasonAccess::manages($user, $match->season)) {
            return true;
        }

        $seasonTeam = $participant->participant;

        return $seasonTeam instanceof SeasonTeam
            && $user->hasRole('delegado')
            && $user->can('teams.manage')
            && $seasonTeam->effectiveDelegate()?->id === $user->id;
    }
}
