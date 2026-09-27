<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SeasonTeamPlayerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SeasonTeamPlayer extends Model
{
    use HasFactory;

    /**
     * Tabla singular ('season_team_player'), consistente con season_team.
     */
    protected $table = 'season_team_player';

    protected $fillable = [
        'season_team_id',
        'player_id',
        'shirt_number',
        'position_id',
        'is_captain',
        'status',
        'enrolled_at',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'is_captain' => 'boolean',
            'status' => SeasonTeamPlayerStatus::class,
            'enrolled_at' => 'date',
        ];
    }

    public function seasonTeam(): BelongsTo
    {
        return $this->belongsTo(SeasonTeam::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(SportPosition::class, 'position_id');
    }

    public function matchEvents(): HasMany
    {
        return $this->hasMany(MatchEvent::class);
    }

    public function suspensions(): HasMany
    {
        return $this->hasMany(Suspension::class);
    }

    public function lineups(): HasMany
    {
        return $this->hasMany(EventLineup::class);
    }

    /**
     * Participaciones individuales (atletismo, ajedrez). Igual que en
     * SeasonTeam: event_participants no tiene FK real hacia esta tabla,
     * revisar esta relacion antes de borrar una inscripcion.
     */
    public function eventParticipants(): MorphMany
    {
        return $this->morphMany(EventParticipant::class, 'participant');
    }
}
