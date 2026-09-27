<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class MatchPeriodPolicy
{
    /**
     * Periodos (sets/tiempos) de un partido: permiso matches.manage.
     * Que el organizador gestione ESE partido lo valida
     * MatchPeriodService con SeasonAccess.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('matches.manage');
    }
}
