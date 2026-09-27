<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Suspension;
use App\Models\User;
use App\Support\SeasonAccess;

class SuspensionPolicy
{
    /**
     * Sanciones: permiso matches.manage; el organizador solo en
     * temporadas de sus torneos.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('matches.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('organizador') && $user->can('matches.manage');
    }

    public function update(User $user, Suspension $suspension): bool
    {
        return SeasonAccess::manages($user, $suspension->season);
    }

    public function delete(User $user, Suspension $suspension): bool
    {
        return SeasonAccess::manages($user, $suspension->season);
    }
}
