<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamesEditionMedal extends Model
{
    use HasFactory;

    protected $fillable = [
        'games_edition_id',
        'team_id',
        'gold',
        'silver',
        'bronze',
    ];

    public function gamesEdition(): BelongsTo
    {
        return $this->belongsTo(GamesEdition::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
