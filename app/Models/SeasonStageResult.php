<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una fila del podio directo de una fase: puesto, delegacion y, opcional, el participante. */
class SeasonStageResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_stage_id',
        'place',
        'team_id',
        'season_team_player_id',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'place' => 'integer',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(SeasonStage::class, 'season_stage_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function seasonTeamPlayer(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamPlayer::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
