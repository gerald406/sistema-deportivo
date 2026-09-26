<?php

declare(strict_types=1);

namespace App\Enums;

enum ParticipantType: string
{
    case SeasonTeam = 'season_team';
    case SeasonTeamPlayer = 'season_team_player';
}
