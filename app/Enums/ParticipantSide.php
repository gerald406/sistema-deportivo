<?php

declare(strict_types=1);

namespace App\Enums;

enum ParticipantSide: string
{
    case Home = 'home';
    case Away = 'away';
}
