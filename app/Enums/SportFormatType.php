<?php

declare(strict_types=1);

namespace App\Enums;

enum SportFormatType: string
{
    case HeadToHead = 'head_to_head';
    case IndividualRanked = 'individual_ranked';
    case TeamRelay = 'team_relay';

    public function label(): string
    {
        return match ($this) {
            self::HeadToHead => 'Enfrentamiento (2 lados)',
            self::IndividualRanked => 'Ranking individual',
            self::TeamRelay => 'Posta por equipos',
        };
    }
}
