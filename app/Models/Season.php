<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SeasonStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'sport_id',
        'games_edition_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => SeasonStatus::class,
            'is_active' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function gamesEdition(): BelongsTo
    {
        return $this->belongsTo(GamesEdition::class);
    }

    public function scoringConfigs(): HasMany
    {
        return $this->hasMany(ScoringConfig::class);
    }

    public function seasonTeams(): HasMany
    {
        return $this->hasMany(SeasonTeam::class);
    }

    public function matchdays(): HasMany
    {
        return $this->hasMany(Matchday::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    public function suspensions(): HasMany
    {
        return $this->hasMany(Suspension::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(SeasonStanding::class);
    }
}
