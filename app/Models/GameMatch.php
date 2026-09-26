<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MatchModality;
use App\Enums\MatchStatus;
use App\Enums\RoundType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Se llama GameMatch, no Match, porque "match" es palabra reservada
 * de PHP 8 (la expresion match) y no puede usarse como nombre de clase.
 */
class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'season_id',
        'matchday_id',
        'discipline_id',
        'venue_id',
        'round_type',
        'modality',
        'scheduled_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'round_type' => RoundType::class,
            'modality' => MatchModality::class,
            'scheduled_at' => 'datetime',
            'status' => MatchStatus::class,
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class, 'match_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(MatchPeriod::class, 'match_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'match_id');
    }

    public function suspensions(): HasMany
    {
        return $this->hasMany(Suspension::class, 'match_id');
    }

    public function reopenLogs(): HasMany
    {
        return $this->hasMany(MatchReopenLog::class, 'match_id');
    }
}
