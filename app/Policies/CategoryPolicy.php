<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Category es un catalogo global (no tiene dueno): basta el permiso
     * categories.manage, que el RolePermissionSeeder solo da al admin.
     * El admin igual pasa por Gate::before; esta policy existe para que
     * si mas adelante se le da el permiso a otro rol, funcione sin tocar
     * el componente.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('categories.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('categories.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('categories.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('categories.manage');
    }
}
