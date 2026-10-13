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
        'total_points',
    ];

    protected function casts(): array
    {
        return [
            'gold' => 'integer',
            'silver' => 'integer',
            'bronze' => 'integer',
            'total_points' => 'integer',
        ];
    }

    public function gamesEdition(): BelongsTo
    {
        return $this->belongsTo(GamesEdition::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
