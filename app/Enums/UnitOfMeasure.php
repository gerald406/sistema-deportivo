<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitOfMeasure: string
{
    case Time = 'time';
    case Distance = 'distance';
    case Points = 'points';
    case Sets = 'sets';
}
