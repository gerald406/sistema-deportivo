<?php

declare(strict_types=1);

namespace App\Enums;

enum SeasonStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'En curso',
            self::Closed => 'Cerrada',
        };
    }

    /** Clases Tailwind del badge de estado. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-amber-100 text-amber-700',
            self::Active => 'bg-emerald-100 text-emerald-700',
            self::Closed => 'bg-gray-100 text-gray-600',
        };
    }
}
