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
     * organizador del torneo y el admin (Gate::before), en Borrador o En
     * curso; nadie en Cerrada. El delegado solo las ve.
     * Las reglas que dependen de la temporada destino (que aun no existe
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

    /**
     * Restriccion sobre season_team: inscripciones cuyo delegado EFECTIVO
     * es $user. Replica en SQL la regla de SeasonTeam::effectiveDelegate():
     * el delegado de la temporada, si existe, tiene precedencia; solo si es
     * null cuenta el delegado general del club (teams.delegate_id).
     *
     * Unica version SQL de esa regla: la usan SeasonTeamService::scopeVisibleTo()
     * y PlayerPolicy::managedRosterConstraint().
     */
    public static function effectiveDelegateConstraint(User $user): Closure
    {
        return function ($seasonTeam) use ($user) {
            // where(closure) agrupa el OR entre parentesis: sin esto
            // romperia la correlacion de un whereHas externo.
            $seasonTeam->where(function ($q) use ($user) {
                $q->where('season_team.delegate_id', $user->id)
                    ->orWhere(function ($q) use ($user) {
                        $q->whereNull('season_team.delegate_id')
                            ->whereHas('team', fn ($team) => $team->where('delegate_id', $user->id));
                    });
            });
        };
    }

    private function isTournamentOrganizer(User $user, SeasonTeam $seasonTeam): bool
    {
        return $user->hasRole('organizador')
            && $user->can('seasons.manage')
            && $seasonTeam->season->tournament->organizer_id === $user->id;
    }
}
