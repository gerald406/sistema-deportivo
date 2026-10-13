<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\User;
use App\Services\SeasonTeamPlayerService;

class SeasonTeamPlayerPolicy
{
    /**
     * El plantel de una inscripcion lo gestionan: su delegado EFECTIVO
     * (SeasonTeam::effectiveDelegate(), regla 5 de CLAUDE.md), el
     * organizador de la olimpiada y el admin (Gate::before). Que la temporada
     * no este cerrada lo valida SeasonTeamPlayerService.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    /**
     * Mismo alcance que el listado de /planteles (una sola fuente de
     * verdad): SeasonTeamPlayerService::scopeVisibleTo(). El admin pasa por
     * Gate::before.
     */
    public function view(User $user, SeasonTeamPlayer $entry): bool
    {
        return app(SeasonTeamPlayerService::class)->isVisibleTo($user, $entry);
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
     * Generar el carnet (fotocheck) o la ficha de inscripcion de ESTA
     * inscripcion: admin siempre (Gate::before); el organizador si la olimpiada
     * de la temporada es suyo; el delegado si es el delegado EFECTIVO del
     * equipo en esa temporada (SeasonTeam::effectiveDelegate()).
     * Es la unica regla para todos los PDFs por jugador: los Controllers
     * llaman a authorize('printCard', ...) en vez de tener cada uno su
     * propio authorizePlayer() privado como en el proyecto de referencia.
     */
    public function printCard(User $user, SeasonTeamPlayer $entry): bool
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
            && $seasonTeam->season->gamesEdition->organizer_id === $user->id) {
            return true;
        }

        return $user->hasRole('delegado')
            && $user->can('teams.manage')
            && $seasonTeam->effectiveDelegate()?->id === $user->id;
    }
}
