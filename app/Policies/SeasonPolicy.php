<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Season;
use App\Models\Tournament;
use App\Models\User;

class SeasonPolicy
{
    /**
     * Una Season pertenece a un Tournament, que a su vez tiene un
     * organizer_id. El delegado no administra seasons, pero necesita
     * verlas para inscribir a su equipo (season_team), por eso view()
     * es mas permisivo que update()/delete().
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    public function view(User $user, Season $season): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    /**
     * "Flujo de permisos" punto 2 de CLAUDE.md: el organizador crea
     * temporadas SOLO dentro de un torneo que el admin le asigno. Aqui aun
     * no se conoce el torneo destino, asi que se exige tener al menos uno;
     * que el tournament_id enviado sea suyo lo revalida
     * SeasonService::ensureCanUseTournament().
     */
    public function create(User $user): bool
    {
        return $user->hasRole('organizador')
            && $user->can('seasons.manage')
            && Tournament::where('organizer_id', $user->id)->exists();
    }

    public function update(User $user, Season $season): bool
    {
        return $user->hasRole('organizador')
            && $user->can('seasons.manage')
            && $season->tournament->organizer_id === $user->id;
    }

    public function delete(User $user, Season $season): bool
    {
        return $user->hasRole('organizador')
            && $user->can('seasons.manage')
            && $season->tournament->organizer_id === $user->id;
    }

    /**
     * Reportes PDF de la temporada (Padron de Jugadores, Reporte Gerencial):
     * el admin (Gate::before) y el organizador del torneo. El delegado no:
     * el padron trae documentos y fechas de nacimiento de todos los equipos.
     */
    public function printReports(User $user, Season $season): bool
    {
        return $user->hasRole('organizador')
            && $season->tournament->organizer_id === $user->id;
    }

    /**
     * Accion especifica del negocio (no un CRUD generico): reabrir un
     * partido cerrado de la temporada. Se deja aqui porque la decision
     * de "quien puede reabrir" es a nivel de temporada/torneo, no de
     * partido individual.
     */
    public function reopenMatches(User $user, Season $season): bool
    {
        return $user->hasRole('organizador')
            && $user->can('matches.reopen')
            && $season->tournament->organizer_id === $user->id;
    }
}
