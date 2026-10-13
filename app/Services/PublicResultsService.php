<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\SeasonStatus;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\GamesEdition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Zona publica (sin login): que olimpiadas/temporadas se muestran.
 *
 * DECISION (Paso 0 de la parte publica): no hay flag is_public. Es publico
 * todo lo ACTIVO: olimpiada is_active + temporada is_active + estado En curso
 * o Cerrada. Las temporadas en Borrador no se publican (se estan armando:
 * inscripciones y fixture incompletos). Para ocultar algo, se desactiva el
 * olimpiada o la temporada, que ya existe en los CRUDs.
 */
class PublicResultsService
{
    /** @return Builder<Season> */
    public function visibleSeasons(): Builder
    {
        return Season::query()
            ->where('seasons.is_active', true)
            ->whereIn('seasons.status', [SeasonStatus::Active->value, SeasonStatus::Closed->value])
            ->whereHas('gamesEdition', fn ($t) => $t->where('is_active', true));
    }

    /** Olimpiadas con al menos una temporada publica. */
    public function visibleGamesEditions(): Collection
    {
        return GamesEdition::query()
            ->whereIn('id', $this->visibleSeasons()->select('games_edition_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'logo']);
    }

    /** Temporadas publicas de una olimpiada: primero las En curso, luego las mas recientes. */
    public function seasonsOf(int $gamesEditionId): Collection
    {
        return $this->visibleSeasons()
            ->with('sport:id,name')
            ->where('games_edition_id', $gamesEditionId)
            ->orderByRaw('status = ? DESC', [SeasonStatus::Active->value])
            ->orderByDesc('start_date')
            ->get(['id', 'games_edition_id', 'sport_id', 'name', 'status']);
    }

    /**
     * Cifras reales para el contador animado de la landing (la referencia
     * mostraba numeros fijos inventados).
     *
     * @return array{gamesEditions: int, teams: int, players: int, matches: int}
     */
    public function landingStats(): array
    {
        return [
            'gamesEditions' => GamesEdition::where('is_active', true)->count(),
            'teams' => Team::where('is_active', true)->count(),
            'players' => Player::where('is_active', true)->count(),
            'matches' => GameMatch::whereIn('status', [MatchStatus::Played->value, MatchStatus::Walkover->value])->count(),
        ];
    }

    /** Una temporada solo si es publica (los ids vienen del navegador). */
    public function findVisibleSeason(?int $seasonId): ?Season
    {
        return $seasonId ? $this->visibleSeasons()->with('gamesEdition', 'sport')->find($seasonId) : null;
    }
}
