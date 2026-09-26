<?php

declare(strict_types=1);

namespace App\Enums;

enum MatchStatus: string
{
    case Pending = 'pending';
    case Played = 'played';
    case Walkover = 'walkover';
    case Canceled = 'canceled';
}
