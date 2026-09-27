<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\User;

class SeasonTeamPlayerPolicy
{
    /**
     * El plantel de una inscripcion lo gestionan: su delegado EFECTIVO
     * (SeasonTeam::effectiveDelegate(), regla 5 de CLAUDE.md), el
     * organizador del torneo y el admin (Gate::before). Que la temporada
     * no este cerrada lo valida SeasonTeamPlayerService.
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

    public function update(User $user, SeasonTeamPlayer $entry): bool
    {
        return self::managesSeasonTeam($user, $entry->seasonTeam);
    }

    public function delete(User $user, SeasonTeamPlayer $entry): bool
    {
        return self::managesSeasonTeam($user, $entry->seasonTeam);
    }

    /**
     * Publica y estatica: SeasonTeamPlayerService la reusa para validar
     * el equipo destino al AGREGAR un jugador (create() no lo conoce).
     */
    public static function managesSeasonTeam(User $user, SeasonTeam $seasonTeam): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('organizador') && $user->can('seasons.manage')
            && $seasonTeam->season->tournament->organizer_id === $user->id) {
            return true;
        }

        return $user->hasRole('delegado')
            && $user->can('teams.manage')
            && $seasonTeam->effectiveDelegate()?->id === $user->id;
    }
}
