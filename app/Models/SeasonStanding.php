<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonStanding extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'season_team_id',
        'matches_played',
        'wins',
        'draws',
        'losses',
        'walkovers_against',
        'points',
        'extra_stats',
    ];

    protected function casts(): array
    {
        return [
            'extra_stats' => 'array',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function seasonTeam(): BelongsTo
    {
        return $this->belongsTo(SeasonTeam::class);
    }
}
