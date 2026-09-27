<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    /**
     * Venue es un catalogo global sin dueno: basta venues.manage, que el
     * RolePermissionSeeder da a admin y organizador (el organizador
     * necesita dar de alta las sedes donde programa sus partidos).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('venues.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('venues.manage');
    }

    public function update(User $user, Venue $venue): bool
    {
        return $user->can('venues.manage');
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->can('venues.manage');
    }
}
