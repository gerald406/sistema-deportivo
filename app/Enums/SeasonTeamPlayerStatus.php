<?php

declare(strict_types=1);

namespace App\Enums;

enum SeasonTeamPlayerStatus: string
{
    case Active = 'active';
    case Injured = 'injured';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
