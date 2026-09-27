<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class MatchReopenLogPolicy
{
    /**
     * Auditoria de reaperturas: solo lectura. La ven admin y organizador
     * (matches.manage); el organizador solo las de sus torneos (filtro en
     * el componente). No hay create/update/delete: los registros los crea
     * ResultService::reopen() y son inmutables.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('matches.manage');
    }
}
