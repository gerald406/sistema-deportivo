<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamRegistrationService;

class TeamPolicy
{
    /**
     * El delegado gestiona su(s) propio(s) equipo(s) via delegate_id
     * (delegado general del club). La precedencia con el delegado de
     * season_team se resuelve en el contexto de Season/SeasonTeam, no
     * aqui: esta policy protege el recurso Team en si mismo.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    public function view(User $user, Team $team): bool
    {
        if ($this->organizerManages($user, $team)) {
            return true;
        }

        // Delegado general o delegado de temporada en alguna inscripcion
        // (mismo criterio que el listado: TeamRegistrationService::scopeManagedBy).
        return $user->hasRole('delegado')
            && ($team->delegate_id === $user->id
                || $team->seasonTeams()->where('delegate_id', $user->id)->exists());
    }

    /**
     * "Flujo de permisos" punto 3 de CLAUDE.md. CAMBIO DE REGLA: antes el
     * delegado creaba equipos; ahora los crea (y les asigna delegado) el
     * organizador o el admin (Gate::before). El delegado NUNCA crea
     * equipos. TeamRegistrationService::register() lo revalida.
     */
    public function create(User $user): bool
    {
        return $this->isOrganizer($user);
    }

    /**
     * El organizador edita solo los equipos a su cargo (inscritos en un
     * olimpiada suya o creados por el): TeamRegistrationService::scopeManagedBy().
     * El delegado conserva la edicion de SU equipo (nombre, logo), pero
     * nunca reasigna delegate_id: eso lo fuerza
     * TeamRegistrationService::resolveDelegateId().
     */
    public function update(User $user, Team $team): bool
    {
        if ($this->organizerManages($user, $team)) {
            return true;
        }

        return $user->hasRole('delegado')
            && $user->can('teams.manage')
            && $team->delegate_id === $user->id;
    }

    /** Quien no crea equipos (delegado) tampoco los borra; el organizador, solo los suyos. */
    public function delete(User $user, Team $team): bool
    {
        return $this->organizerManages($user, $team);
    }

    private function isOrganizer(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('teams.manage');
    }

    private function organizerManages(User $user, Team $team): bool
    {
        return app(TeamRegistrationService::class)->manages($user, $team);
    }
}
