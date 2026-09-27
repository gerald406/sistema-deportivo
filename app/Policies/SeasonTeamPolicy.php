<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SeasonTeam;
use App\Models\User;

class SeasonTeamPolicy
{
    /**
     * "Flujo de permisos" punto 4 de CLAUDE.md. CAMBIO DE REGLA respecto
     * a Fase 5 (antes "el delegado inscribe sus equipos en Borrador", ya
     * OBSOLETA): las inscripciones las crean, editan y retiran SOLO el
     * organizador del torneo y el admin (Gate::before), en Borrador o En
     * curso; nadie en Cerrada. El delegado solo las ve.
     * Las reglas que dependen de la temporada destino (que aun no existe
     * al llamar a create()) las aplica SeasonTeamService.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('seasons.manage');
    }

    /**
     * Categoria, delegado de temporada y estado de la inscripcion: solo
     * el organizador del torneo (el delegado no se reasigna a si mismo).
     */
    public function update(User $user, SeasonTeam $seasonTeam): bool
    {
        return $this->isTournamentOrganizer($user, $seasonTeam);
    }

    /**
     * Retirar la inscripcion: solo el organizador del torneo. CAMBIO DE
     * REGLA: el delegado efectivo ya no puede retirarla (ni en Borrador).
     */
    public function delete(User $user, SeasonTeam $seasonTeam): bool
    {
        return $this->isTournamentOrganizer($user, $seasonTeam);
    }

    private function isTournamentOrganizer(User $user, SeasonTeam $seasonTeam): bool
    {
        return $user->hasRole('organizador')
            && $user->can('seasons.manage')
            && $seasonTeam->season->tournament->organizer_id === $user->id;
    }
}
