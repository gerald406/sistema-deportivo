<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamesEdition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo',
        'organizer_id',
        'start_date',
        'end_date',
        'host_venue_id',
        'is_active',
        'yellow_cards_limit',
        'captain_red_matches',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            // Reglas disciplinarias: NULL = no se aplica (ver SuspensionService).
            'yellow_cards_limit' => 'integer',
            'captain_red_matches' => 'integer',
        ];
    }

    /** Organizador responsable: gestiona todo lo que cuelga de la olimpiada. */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function hostVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'host_venue_id');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    /** Series de la olimpiada, en el orden que definio el organizador. */
    public function groups(): HasMany
    {
        return $this->hasMany(GamesEditionGroup::class)->orderBy('sort_order')->orderBy('name');
    }

    /** Delegaciones inscritas (con su serie y sus delegados). */
    public function editionTeams(): HasMany
    {
        return $this->hasMany(GamesEditionTeam::class);
    }

    /** Cuadro de puntaje (filas en el orden de las bases). */
    public function events(): HasMany
    {
        return $this->hasMany(GamesEditionEvent::class)->orderBy('sort_order')->orderBy('label');
    }

    /** Podios con puntos (derivado: AwardService::rebuild()). */
    public function awards(): HasMany
    {
        return $this->hasMany(EditionAward::class);
    }

    public function medals(): HasMany
    {
        return $this->hasMany(GamesEditionMedal::class);
    }
}
