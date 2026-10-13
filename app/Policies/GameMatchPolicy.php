<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GameMatch;
use App\Models\User;
use App\Services\GameMatchService;
use App\Support\SeasonAccess;

class GameMatchPolicy
{
    /**
     * Partidos: gestion con permiso matches.manage (admin y organizador);
     * el organizador solo los de competencias de sus olimpiadas. El delegado
     * entra en SOLO LECTURA y ve los partidos de su equipo
     * (GameMatchService::scopeVisibleTo()); create/update/delete lo
     * rechazan porque exigen rol organizador (SeasonAccess::manages()).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('matches.manage') || $user->hasRole('delegado');
    }

    /** Mismo alcance que el listado (una sola fuente de verdad). */
    public function view(User $user, GameMatch $match): bool
    {
        return app(GameMatchService::class)->isVisibleTo($user, $match);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('matches.manage');
    }

    public function update(User $user, GameMatch $match): bool
    {
        return SeasonAccess::manages($user, $match->season);
    }

    public function delete(User $user, GameMatch $match): bool
    {
        return SeasonAccess::manages($user, $match->season);
    }
}
