<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Zona publica (sin login): que torneos/temporadas se muestran.
 *
 * DECISION (Paso 0 de la parte publica): no hay flag is_public. Es publico
 * todo lo ACTIVO: torneo is_active + temporada is_active + estado En curso
 * o Cerrada. Las temporadas en Borrador no se publican (se estan armando:
 * inscripciones y fixture incompletos). Para ocultar algo, se desactiva el
 * torneo o la temporada, que ya existe en los CRUDs.
 */
class PublicResultsService
{
    /** @return Builder<Season> */
    public function visibleSeasons(): Builder
    {
        return Season::query()
            ->where('seasons.is_active', true)
            ->whereIn('seasons.status', [SeasonStatus::Active->value, SeasonStatus::Closed->value])
            ->whereHas('tournament', fn ($t) => $t->where('is_active', true));
    }

    /** Torneos con al menos una temporada publica. */
    public function visibleTournaments(): Collection
    {
        return Tournament::query()
            ->whereIn('id', $this->visibleSeasons()->select('tournament_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'logo']);
    }

    /** Temporadas publicas de un torneo: primero las En curso, luego las mas recientes. */
    public function seasonsOf(int $tournamentId): Collection
    {
        return $this->visibleSeasons()
            ->with('sport:id,name')
            ->where('tournament_id', $tournamentId)
            ->orderByRaw('status = ? DESC', [SeasonStatus::Active->value])
            ->orderByDesc('start_date')
            ->get(['id', 'tournament_id', 'sport_id', 'name', 'status']);
    }

    /** Una temporada solo si es publica (los ids vienen del navegador). */
    public function findVisibleSeason(?int $seasonId): ?Season
    {
        return $seasonId ? $this->visibleSeasons()->with('tournament', 'sport')->find($seasonId) : null;
    }
}
