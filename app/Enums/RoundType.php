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
}
