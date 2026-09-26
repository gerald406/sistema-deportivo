<?php

declare(strict_types=1);

namespace App\Enums;

enum SeasonStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
}
