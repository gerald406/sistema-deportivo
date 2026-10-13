<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

class SeasonTeam extends Model
{
    use HasFactory;

    /**
     * La tabla es singular ('season_team'), no plural, segun el diseno
     * de inscripciones acordado para el dominio.
     */
    protected $table = 'season_team';

    protected $fillable = [
        'season_id',
        'team_id',
        'category_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Memo de editionTeam(): false = aun no resuelto. */
    private GamesEditionTeam|false|null $resolvedEditionTeam = false;

    /**
     * Inscripcion de la delegacion en la olimpiada de esta competencia
     * (games_edition_teams). Es la UNICA fuente de los delegados: no hay
     * delegado por competencia ni delegado general de la delegacion.
     * Se resuelve una vez por instancia (los listados preguntan por fila).
     */
    public function editionTeam(): ?GamesEditionTeam
    {
        if ($this->resolvedEditionTeam === false) {
            $editionId = $this->season?->games_edition_id;

            $this->resolvedEditionTeam = $editionId
                ? GamesEditionTeam::with('delegate:id,name,email', 'substituteDelegate:id,name,email')
                    ->where('games_edition_id', $editionId)
                    ->where('team_id', $this->team_id)
                    ->first()
                : null;
        }

        return $this->resolvedEditionTeam;
    }

    /**
     * Delegado titular y suplente de la delegacion en esta olimpiada (sin
     * nulos). Ambos tienen los mismos permisos. Ningun Service debe
     * reimplementar esta regla: usan delegates(), hasDelegate() o, en SQL,
     * SeasonTeamPolicy::delegateConstraint().
     */
    public function delegates(): Collection
    {
        return $this->editionTeam()?->delegates() ?? collect();
    }

    public function hasDelegate(User $user): bool
    {
        return (bool) $this->editionTeam()?->hasDelegate($user);
    }

    public function roster(): HasMany
    {
        return $this->hasMany(SeasonTeamPlayer::class);
    }

    public function standing(): HasMany
    {
        return $this->hasMany(SeasonStanding::class);
    }

    /**
     * Participaciones en partidos/pruebas como equipo. event_participants
     * es polimorfica y NO tiene FK real hacia season_team: cualquier
     * Service que borre una inscripcion debe revisar esta relacion antes.
     */
    public function eventParticipants(): MorphMany
    {
        return $this->morphMany(EventParticipant::class, 'participant');
    }
}
