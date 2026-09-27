<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class EventParticipantPolicy
{
    /**
     * Carga de resultados: permiso matches.manage. Que el organizador
     * gestione ESE partido (SeasonAccess) y la reapertura
     * (SeasonPolicy::reopenMatches) los valida ResultService.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('matches.manage');
    }
}
