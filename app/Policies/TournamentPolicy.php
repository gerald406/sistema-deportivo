<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
{
    /**
     * El admin bypassa todas las policies via Gate::before en
     * AppServiceProvider, asi que aqui solo se resuelve el caso
     * "organizador dueno del torneo". El delegado no gestiona torneos.
     *
     * "Flujo de permisos" punto 1 de CLAUDE.md: SOLO el admin crea torneos y asigna su
     * organizador (organizer_id). El organizador asignado edita los datos
     * de SU torneo (nombre, logo, estado), pero no lo crea, no lo borra ni
     * cambia su organizador (esto ultimo lo fuerza TournamentService).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    public function view(User $user, Tournament $tournament): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    /**
     * CAMBIO DE REGLA: antes el organizador tambien creaba torneos. Ahora
     * solo el admin, que entra por Gate::before antes de llegar aqui.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Tournament $tournament): bool
    {
        return $user->hasRole('organizador')
            && $user->can('tournaments.manage')
            && $tournament->organizer_id === $user->id;
    }

    /**
     * CAMBIO DE REGLA: si el organizador no crea torneos, tampoco los
     * borra (el torneo se lo asigno el admin). Solo admin, via Gate::before.
     */
    public function delete(User $user, Tournament $tournament): bool
    {
        return false;
    }
}
