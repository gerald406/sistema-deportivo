<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\SeasonStatus;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Zona publica (sin login): que olimpiadas/competencias se muestran.
 *
 * DECISION (Paso 0 de la parte publica): no hay flag is_public. Es publico
 * todo lo ACTIVO: olimpiada is_active + competencia is_active + estado En curso
 * o Cerrada. Las competencias en Borrador no se publican (se estan armando:
 * inscripciones y fixture incompletos). Para ocultar algo, se desactiva el
 * olimpiada o la competencia, que ya existe en los CRUDs.
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

    /** Olimpiadas con al menos una competencia publica, la mas reciente primero. */
    public function visibleGamesEditions(): Collection
    {
        return GamesEdition::query()
            ->whereIn('id', $this->visibleSeasons()->select('games_edition_id'))
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get(['id', 'name', 'logo', 'start_date', 'end_date']);
    }

    /** Competencias publicas de una olimpiada: primero las En curso, luego las mas recientes. */
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
     * Partidos y pruebas publicos de una olimpiada: la programacion (por
     * jugar, del mas proximo al mas lejano) o los resultados (jugados y
     * walkovers, del mas reciente al mas antiguo). Los cancelados no se
     * publican. Los filtros llegan del navegador: aqui solo acotan dentro
     * de las competencias visibles, nunca las amplian.
     *
     * @param  array{sportId?: ?int, seasonId?: ?int, search?: string}  $filters
     * @return Builder<GameMatch>
     */
    public function matchesOf(int $gamesEditionId, bool $played, array $filters = []): Builder
    {
        $term = trim($filters['search'] ?? '');

        return GameMatch::query()
            ->whereIn('season_id', $this->visibleSeasons()
                ->where('games_edition_id', $gamesEditionId)
                ->when($filters['sportId'] ?? null, fn ($q, int $id) => $q->where('sport_id', $id))
                ->when($filters['seasonId'] ?? null, fn ($q, int $id) => $q->where('seasons.id', $id))
                ->select('seasons.id'))
            ->whereIn('status', $played
                ? [MatchStatus::Played->value, MatchStatus::Walkover->value]
                : [MatchStatus::Pending->value])
            ->when($term !== '', fn ($q) => $q->whereHas('participants', fn ($p) => $p->whereHasMorph(
                'participant', [SeasonTeam::class, SeasonTeamPlayer::class],
                fn ($m, string $type) => $type === SeasonTeam::class
                    ? $m->whereHas('team', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                    : $m->where(fn ($w) => $w
                        ->whereHas('player', fn ($pl) => $pl->where('last_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%"))
                        ->orWhereHas('seasonTeam.team', fn ($t) => $t->where('name', 'like', "%{$term}%"))),
            )))
            // Sin fecha al final; con fecha, lo proximo primero (programacion) o lo ultimo primero (resultados).
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at', $played ? 'desc' : 'asc')
            ->orderBy('id', $played ? 'desc' : 'asc');
    }

    /** Una competencia solo si es publica (los ids vienen del navegador). */
    public function findVisibleSeason(?int $seasonId): ?Season
    {
        return $seasonId ? $this->visibleSeasons()->with('gamesEdition', 'sport')->find($seasonId) : null;
    }
}
