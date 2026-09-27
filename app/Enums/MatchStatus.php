<?php

declare(strict_types=1);

namespace App\Enums;

enum MatchStatus: string
{
    case Pending = 'pending';
    case Played = 'played';
    case Walkover = 'walkover';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Programado',
            self::Played => 'Jugado',
            self::Walkover => 'Walkover',
            self::Canceled => 'Cancelado',
        };
    }

    /** Clases Tailwind del badge de estado. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-sky-100 text-sky-700',
            self::Played => 'bg-emerald-100 text-emerald-700',
            self::Walkover => 'bg-amber-100 text-amber-700',
            self::Canceled => 'bg-gray-100 text-gray-500',
        };
    }
}
