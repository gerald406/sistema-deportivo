<?php

declare(strict_types=1);

namespace App\Enums;

enum BetterDirection: string
{
    case Ascending = 'asc';
    case Descending = 'desc';
}
