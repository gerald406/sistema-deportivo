<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GamesEdition;
use App\Models\User;
use App\Services\GamesEditionService;

class GamesEditionPolicy
{
    /**
     * El admin bypassa todas las policies via Gate::before en
     * AppServiceProvider, asi que aqui solo se resuelve el caso
     * "organizador de la olimpiada". El delegado no gestiona olimpiadas.
     *
     * SOLO el admin crea y borra olimpiadas y asigna su organizador
     * (docs/fases/00-especificacion.md, D2). El organizador asignado edita
     * los datos de SU olimpiada; que no cambie organizer_id lo fuerza
     * GamesEditionService.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('editions.manage');
    }

    public function view(User $user, GamesEdition $edition): bool
    {
        return $this->manage($user, $edition);
    }

    /** Solo admin, via Gate::before. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, GamesEdition $edition): bool
    {
        return $this->manage($user, $edition);
    }

    /** Solo admin, via Gate::before. */
    public function delete(User $user, GamesEdition $edition): bool
    {
        return false;
    }

    /**
     * Gestionar lo que cuelga de la olimpiada (series, delegaciones
     * inscritas, competencias): una sola regla, la del Service.
     */
    public function manage(User $user, GamesEdition $edition): bool
    {
        return app(GamesEditionService::class)->manages($user, $edition);
    }
}
