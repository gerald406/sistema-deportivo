<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SportPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'sport_id',
        'name',
        'sort_order',
    ];

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function seasonTeamPlayers(): HasMany
    {
        return $this->hasMany(SeasonTeamPlayer::class, 'position_id');
    }
}
