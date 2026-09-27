<?php

declare(strict_types=1);

namespace App\Enums;

enum MatchModality: string
{
    case BestOf1 = 'best_of_1';
    case BestOf3 = 'best_of_3';
    case BestOf5 = 'best_of_5';
    case SwissRound = 'swiss_round';
    case SingleElimination = 'single_elimination';

    public function label(): string
    {
        return match ($this) {
            self::BestOf1 => 'A un partido / set',
            self::BestOf3 => 'Al mejor de 3',
            self::BestOf5 => 'Al mejor de 5',
            self::SwissRound => 'Sistema suizo',
            self::SingleElimination => 'Eliminación directa',
        };
    }
}
