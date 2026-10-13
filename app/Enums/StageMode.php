<?php

declare(strict_types=1);

namespace App\Enums;

/** Como se resuelve una fase de una competencia. */
enum StageMode: string
{
    case Matches = 'matches';
    case DirectPodium = 'direct_podium';

    public function label(): string
    {
        return match ($this) {
            self::Matches => 'Partidos (llave)',
            self::DirectPodium => 'Podio directo',
        };
    }
}
