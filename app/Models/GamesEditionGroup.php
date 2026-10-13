<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Serie de una olimpiada (Serie A, B, C, D). */
class GamesEditionGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'games_edition_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(GamesEdition::class, 'games_edition_id');
    }

    /** Delegaciones inscritas en esta serie. */
    public function editionTeams(): HasMany
    {
        return $this->hasMany(GamesEditionTeam::class, 'group_id');
    }
}
