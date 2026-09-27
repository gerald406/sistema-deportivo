<?php

declare(strict_types=1);

namespace App\Enums;

enum RoundType: string
{
    case Regular = 'regular';
    case Heat = 'heat';
    case QuarterFinal = 'quarterfinal';
    case SemiFinal = 'semifinal';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Fase regular',
            self::Heat => 'Serie / eliminatoria',
            self::QuarterFinal => 'Cuartos de final',
            self::SemiFinal => 'Semifinal',
            self::Final => 'Final',
        };
    }
}
