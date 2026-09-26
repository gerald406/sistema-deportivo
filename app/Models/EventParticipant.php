<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ParticipantSide;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EventParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'participant_type',
        'participant_id',
        'side',
        'lane_or_board',
        'result_value',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'side' => ParticipantSide::class,
            'result_value' => 'decimal:4',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /**
     * Relacion polimorfica manual: usa el morphMap registrado en
     * AppServiceProvider ('season_team' => SeasonTeam::class,
     * 'season_team_player' => SeasonTeamPlayer::class), consistente con
     * el CHECK constraint de la migracion.
     */
    public function participant(): MorphTo
    {
        return $this->morphTo(name: 'participant', type: 'participant_type', id: 'participant_id');
    }

    public function lineups(): HasMany
    {
        return $this->hasMany(EventLineup::class);
    }

    /**
     * Medalla calculada a partir del puesto, nunca almacenada: evita que
     * un dato derivado pueda desincronizarse del puesto real.
     */
    public function medal(): ?string
    {
        return match ($this->position) {
            1 => 'gold',
            2 => 'silver',
            3 => 'bronze',
            default => null,
        };
    }
}
