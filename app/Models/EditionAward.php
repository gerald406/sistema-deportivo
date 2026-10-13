<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un puesto de podio de una competencia con sus puntos para el puntaje
 * general. Derivado: solo lo escribe AwardService::rebuild().
 */
class EditionAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'games_edition_id',
        'season_id',
        'games_edition_event_id',
        'team_id',
        'place',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'place' => 'integer',
            'points' => 'integer',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(GamesEdition::class, 'games_edition_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(GamesEditionEvent::class, 'games_edition_event_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
