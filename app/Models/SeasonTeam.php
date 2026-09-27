<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'delegate_id',
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

    /**
     * Delegado especifico de esta temporada (puede ser null).
     */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    /**
     * Regla de precedencia entre delegados (resuelve el punto 2.5 de la
     * revision): el delegado de la temporada, si existe, sustituye al
     * delegado general del club para esta inscripcion. Si no se definio
     * uno especifico para la temporada, se usa el general del equipo
     * como fallback. Ningun Service debe reimplementar esta regla; deben
     * llamar a este accessor.
     */
    public function effectiveDelegate(): ?User
    {
        return $this->delegate ?? $this->team?->delegate;
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
