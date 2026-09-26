<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo_path',
        'delegate_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Delegado general del club. Cuando el equipo esta inscrito en una
     * temporada con su propio delegado (season_team.delegate_id), ese
     * delegado especifico tiene precedencia sobre este para esa
     * temporada: ver SeasonTeam::effectiveDelegate().
     */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function seasonTeams(): HasMany
    {
        return $this->hasMany(SeasonTeam::class);
    }

    public function medals(): HasMany
    {
        return $this->hasMany(GamesEditionMedal::class);
    }
}
