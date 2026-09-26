<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suspension extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'season_team_player_id',
        'match_id',
        'reason',
        'matches_suspended',
        'is_served',
    ];

    protected function casts(): array
    {
        return [
            'is_served' => 'boolean',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function seasonTeamPlayer(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamPlayer::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
