<?php

declare(strict_types=1);

namespace App\Enums;

enum SeasonTeamPlayerStatus: string
{
    case Active = 'active';
    case Injured = 'injured';
    case Suspended = 'suspended';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Habilitado',
            self::Injured => 'Lesionado',
            self::Suspended => 'Suspendido',
            self::Inactive => 'Baja',
        };
    }

    /** Clases Tailwind del badge de estado. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-700',
            self::Injured => 'bg-amber-100 text-amber-700',
            self::Suspended => 'bg-red-100 text-red-700',
            self::Inactive => 'bg-gray-100 text-gray-600',
        };
    }
}
