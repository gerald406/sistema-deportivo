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
        'director_name',
        'logo_path',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Inscripciones de la delegacion en olimpiadas. Ahi viven su serie y
     * sus delegados titular y suplente (games_edition_teams).
     */
    public function editionTeams(): HasMany
    {
        return $this->hasMany(GamesEditionTeam::class);
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
