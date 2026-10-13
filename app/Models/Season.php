<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\SeasonStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'games_edition_id',
        'games_edition_event_id',
        'sport_id',
        'category_id',
        'discipline_id',
        'gender',
        'name',
        'start_date',
        'end_date',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'gender' => Gender::class,
            'status' => SeasonStatus::class,
            'is_active' => 'boolean',
        ];
    }

    /** La olimpiada a la que pertenece la competencia (obligatoria). */
    public function gamesEdition(): BelongsTo
    {
        return $this->belongsTo(GamesEdition::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /** Fila del cuadro de puntaje de la que salio (puntos y topes de plantel). */
    public function event(): BelongsTo
    {
        return $this->belongsTo(GamesEditionEvent::class, 'games_edition_event_id');
    }

    /** Nivel (Libre, Master, Super Master): fija la edad minima del plantel. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Prueba, cuando la competencia es de una sola (atletismo). */
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    /** Fases de la competencia (series, fase final o final de podio directo). */
    public function stages(): HasMany
    {
        return $this->hasMany(SeasonStage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scoringConfigs(): HasMany
    {
        return $this->hasMany(ScoringConfig::class);
    }

    public function seasonTeams(): HasMany
    {
        return $this->hasMany(SeasonTeam::class);
    }

    public function matchdays(): HasMany
    {
        return $this->hasMany(Matchday::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    public function suspensions(): HasMany
    {
        return $this->hasMany(Suspension::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(SeasonStanding::class);
    }
}
