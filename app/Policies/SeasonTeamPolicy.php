<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SeasonTeam;
use App\Models\User;
use App\Services\SeasonTeamService;
use Closure;

class SeasonTeamPolicy
{
    /**
     * "Flujo de permisos" punto 4 de CLAUDE.md. CAMBIO DE REGLA respecto
     * a Fase 5 (antes "el delegado inscribe sus equipos en Borrador", ya
     * OBSOLETA): las inscripciones las crean, editan y retiran SOLO el
     * organizador de la olimpiada y el admin (Gate::before), en Borrador o En
     * curso; nadie en Cerrada. El delegado solo las ve.
     * Las reglas que dependen de la competencia destino (que aun no existe
     * al llamar a create()) las aplica SeasonTeamService.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    /**
     * Mismo alcance que el listado de /inscripciones (una sola fuente de
     * verdad): SeasonTeamService::scopeVisibleTo(). El admin pasa por
     * Gate::before.
     */
    public function view(User $user, SeasonTeam $seasonTeam): bool
    {
        return app(SeasonTeamService::class)->isVisibleTo($user, $seasonTeam);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('seasons.manage');
    }

    /**
     * Categoria y estado de la inscripcion: solo el organizador de la
     * olimpiada.
     */
    public function update(User $user, SeasonTeam $seasonTeam): bool
    {
        return $this->isGamesEditionOrganizer($user, $seasonTeam);
    }

    /**
     * Retirar la inscripcion: solo el organizador de la olimpiada. CAMBIO DE
     * REGLA: el delegado ya no puede retirarla (ni en Borrador).
     */
    public function delete(User $user, SeasonTeam $seasonTeam): bool
    {
        return $this->isGamesEditionOrganizer($user, $seasonTeam);
    }

    /**
     * Padron de Participantes (PDF) de UNA inscripcion: el organizador del
     * olimpiada y los delegados de la delegacion (admin por Gate::before).
     * Es el mismo alcance de /inscripciones, asi que reutiliza
     * isVisibleTo() en vez de otra regla. El padron de TODOS los equipos de
     * la competencia sigue siendo SeasonPolicy::printReports (sin delegado).
     */
    public function printRoster(User $user, SeasonTeam $seasonTeam): bool
    {
        return app(SeasonTeamService::class)->isVisibleTo($user, $seasonTeam);
    }

    /**
     * Restriccion sobre season_team: inscripciones de una delegacion de la
     * que $user es delegado titular o suplente EN LA OLIMPIADA de esa
     * competencia (games_edition_teams). Version SQL de
     * SeasonTeam::hasDelegate().
     *
     * Unica version SQL de esa regla: la usan SeasonTeamService::scopeVisibleTo(),
     * PlayerPolicy::managedRosterConstraint(), DashboardService,
     * PlayerCardService y el listado de relevos.
     */
    public static function delegateConstraint(User $user): Closure
    {
        return function ($seasonTeam) use ($user) {
            $seasonTeam->whereExists(function ($sub) use ($user) {
                $sub->selectRaw('1')
                    ->from('games_edition_teams as ged')
                    ->join('seasons as ged_season', 'ged_season.games_edition_id', '=', 'ged.games_edition_id')
                    ->whereColumn('ged_season.id', 'season_team.season_id')
                    ->whereColumn('ged.team_id', 'season_team.team_id')
                    ->where(fn ($d) => $d->where('ged.delegate_id', $user->id)->orWhere('ged.substitute_delegate_id', $user->id));
            });
        };
    }

    private function isGamesEditionOrganizer(User $user, SeasonTeam $seasonTeam): bool
    {
        return $user->hasRole('organizador')
            && $user->can('seasons.manage')
            && $seasonTeam->season->gamesEdition->organizer_id === $user->id;
    }
}
