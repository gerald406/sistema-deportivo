<?php

declare(strict_types=1);

namespace App\Enums;

enum EditionTeamStatus: string
{
    case Registered = 'registered';
    case Withdrawn = 'withdrawn';
    case Disqualified = 'disqualified';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Inscrita',
            self::Withdrawn => 'Retirada',
            self::Disqualified => 'Descalificada',
        };
    }

    /** Clases Tailwind del badge de estado. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Registered => 'bg-emerald-100 text-emerald-700',
            self::Withdrawn => 'bg-gray-100 text-gray-600',
            self::Disqualified => 'bg-red-100 text-red-700',
        };
    }
}
