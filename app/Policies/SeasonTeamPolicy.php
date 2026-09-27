<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SeasonStatus;
use App\Models\SeasonTeam;
use App\Models\User;

class SeasonTeamPolicy
{
    /**
     * Reglas confirmadas (Fase 5):
     *   - El delegado inscribe directamente SUS equipos; organizador (de
     *     su torneo) y admin inscriben cualquiera.
     *   - Solo se inscribe en temporadas en Borrador; organizador/admin
     *     tambien En curso; nadie en Cerrada.
     * Las reglas que dependen de la temporada/equipo destino (que aun no
     * existen al llamar a create()) las aplica SeasonTeamService.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    public function create(User $user): bool
    {
        return ($user->hasRole('organizador') && $user->can('seasons.manage'))
            || ($user->hasRole('delegado') && $user->can('teams.manage'));
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
     * Retirar la inscripcion: el organizador del torneo, o el delegado
     * EFECTIVO de la inscripcion (SeasonTeam::effectiveDelegate(), regla
     * 5 de CLAUDE.md) mientras la temporada siga en Borrador.
     */
    public function delete(User $user, SeasonTeam $seasonTeam): bool
    {
        if ($this->isTournamentOrganizer($user, $seasonTeam)) {
            return true;
        }

        return $user->hasRole('delegado')
            && $user->can('teams.manage')
            && $seasonTeam->season->status === SeasonStatus::Draft
            && $seasonTeam->effectiveDelegate()?->id === $user->id;
    }

    private function isTournamentOrganizer(User $user, SeasonTeam $seasonTeam): bool
    {
        return $user->hasRole('organizador')
            && $user->can('seasons.manage')
            && $seasonTeam->season->tournament->organizer_id === $user->id;
    }
}
