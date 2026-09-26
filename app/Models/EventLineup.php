<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventLineup extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_participant_id',
        'season_team_player_id',
        'leg_order',
    ];

    public function eventParticipant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class);
    }

    public function seasonTeamPlayer(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamPlayer::class);
    }
}
