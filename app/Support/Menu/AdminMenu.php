<?php

declare(strict_types=1);

namespace App\Support\Menu;

use App\Models\User;

class AdminMenu
{
    /**
     * Devuelve el menu ya filtrado por rol/permiso del usuario. Un item
     * con hijos se descarta si ninguno de sus hijos quedo visible, para
     * no mostrar un dropdown vacio en el sidebar.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        $items = config('admin-menu.items', []);

        return array_values(array_filter(array_map(
            fn (array $item) => self::resolveItem($item, $user),
            $items
        )));
    }

    private static function resolveItem(array $item, User $user): ?array
    {
        if (! empty($item['children'])) {
            $children = array_values(array_filter(
                $item['children'],
                fn (array $child) => self::isVisible($child, $user)
            ));

            if ($children === []) {
                return null;
            }

            $item['children'] = $children;

            return $item;
        }

        return self::isVisible($item, $user) ? $item : null;
    }

    private static function isVisible(array $item, User $user): bool
    {
        if (isset($item['role'])) {
            return $user->hasRole($item['role']);
        }

        if (isset($item['permission'])) {
            return $user->can($item['permission']);
        }

        return true;
    }
}
