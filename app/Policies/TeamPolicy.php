<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

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
        if ($user->hasAnyRole(['admin', 'organizador'])) {
            return true;
        }

        return $team->delegate_id === $user->id;
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
     * El organizador edita cualquier equipo (Team es un catalogo global,
     * no pertenece a un torneo). El delegado conserva la edicion de SU
     * equipo (nombre, logo), pero nunca reasigna delegate_id: eso lo
     * fuerza TeamRegistrationService::resolveDelegateId().
     */
    public function update(User $user, Team $team): bool
    {
        if ($this->isOrganizer($user)) {
            return true;
        }

        return $user->hasRole('delegado')
            && $user->can('teams.manage')
            && $team->delegate_id === $user->id;
    }

    /** CAMBIO DE REGLA: quien no crea equipos tampoco los borra. */
    public function delete(User $user, Team $team): bool
    {
        return $this->isOrganizer($user);
    }

    private function isOrganizer(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('teams.manage');
    }
}
