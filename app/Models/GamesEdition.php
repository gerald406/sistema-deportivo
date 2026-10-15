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
        'registration_deadline',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'registration_deadline' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * El delegado aun puede inscribir a su delegacion en disciplinas (D22):
     * hasta el final del dia de cierre, en hora de la aplicacion. Sin
     * fecha = siempre abierto.
     */
    public function registrationOpen(): bool
    {
        return $this->registration_deadline === null || now()->lte($this->registration_deadline->copy()->endOfDay());
    }

    /** Organizador responsable: gestiona todo lo que cuelga de la olimpiada. */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
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
