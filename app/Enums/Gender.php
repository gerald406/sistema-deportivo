<?php

declare(strict_types=1);

namespace App\Enums;

enum Gender: string
{
    case Male = 'M';
    case Female = 'F';
    case Mixed = 'X';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Varones',
            self::Female => 'Damas',
            self::Mixed => 'Mixto',
        };
    }
}
