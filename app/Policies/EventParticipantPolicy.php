<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class EventParticipantPolicy
{
    /**
     * /resultados: gestion con permiso matches.manage. El delegado entra en
     * SOLO LECTURA (partidos de su equipo, GameMatchService::scopeVisibleTo()).
     * Cargar el resultado exige SeasonAccess::manages() y reabrirlo
     * SeasonPolicy::reopenMatches (ambos con rol organizador del torneo);
     * los revalida ResultService.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('matches.manage') || $user->hasRole('delegado');
    }
}
