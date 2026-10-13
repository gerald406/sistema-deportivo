<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamRegistrationService;

class TeamPolicy
{
    /**
     * La delegacion (Team) la gestionan el organizador y el admin
     * (Gate::before). El delegado NO entra a /equipos ni edita su
     * delegacion: solo registra participantes
     * (docs/fases/00-especificacion.md, D4). Sus delegados viven en la
     * inscripcion de la delegacion en la olimpiada (GamesEditionTeam).
     */
    public function viewAny(User $user): bool
    {
        return $this->isOrganizer($user);
    }

    public function view(User $user, Team $team): bool
    {
        return $this->organizerManages($user, $team);
    }

    /**
     * "Flujo de permisos" punto 3 de CLAUDE.md. CAMBIO DE REGLA: antes el
     * delegado creaba equipos; ahora los crea el
     * organizador o el admin (Gate::before). El delegado NUNCA crea
     * equipos. TeamRegistrationService::register() lo revalida.
     */
    public function create(User $user): bool
    {
        return $this->isOrganizer($user);
    }

    /**
     * El organizador edita solo las delegaciones a su cargo (inscritas en
     * una olimpiada suya o creadas por el):
     * TeamRegistrationService::scopeManagedBy(). El delegado no las edita.
     */
    public function update(User $user, Team $team): bool
    {
        return $this->organizerManages($user, $team);
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
