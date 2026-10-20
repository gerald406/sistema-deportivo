<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EditionTeamStatus;
use App\Enums\MatchStatus;
use App\Enums\SeasonStatus;
use App\Enums\StageMode;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Zona publica (sin login): que olimpiadas/competencias se muestran.
 *
 * DECISION (Paso 0 de la parte publica): no hay flag is_public. Es publico
 * todo lo ACTIVO: olimpiada is_active + competencia is_active + estado En curso
 * o Cerrada. Las competencias en Borrador no se publican (se estan armando:
 * inscripciones y fixture incompletos). Para ocultar algo, se desactiva el
 * olimpiada o la competencia, que ya existe en los CRUDs.
 *
 * La portada se recorre por SERIE: cada partido y cada podio pertenece a la
 * serie de su fase (season_stages.group_id). Lo que no tiene serie (fase
 * final, finales de podio, partidos sin fase) va al grupo FINALS.
 */
class PublicResultsService
{
    /** Clave del grupo "Finales y podios": todo lo que no es de una serie. */
    public const FINALS = 'final';

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
     * @param  array{series?: int|string|null, sportId?: ?int, seasonId?: ?int, search?: string}  $filters
     * @return Builder<GameMatch>
     */
    public function matchesOf(int $gamesEditionId, bool $played, array $filters = []): Builder
    {
        $term = trim($filters['search'] ?? '');

        return $this->matchInSeries(GameMatch::query(), $filters['series'] ?? null)
            ->whereIn('season_id', $this->visibleSeasonIds($gamesEditionId, $filters))
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

    /**
     * Podios directos ya registrados (1.º, 2.º y 3.º), por deporte y
     * competencia. Mismos filtros que matchesOf(); la busqueda mira la
     * delegacion y el participante.
     *
     * @param  array{series?: int|string|null, sportId?: ?int, seasonId?: ?int, search?: string}  $filters
     * @return Builder<SeasonStage>
     */
    public function podiumsOf(int $gamesEditionId, array $filters = []): Builder
    {
        $term = trim($filters['search'] ?? '');

        return $this->stageInSeries(SeasonStage::query(), $filters['series'] ?? null)
            ->where('mode', StageMode::DirectPodium->value)
            ->whereIn('season_id', $this->visibleSeasonIds($gamesEditionId, $filters))
            ->where(fn ($q) => $q
                ->whereHas('results', fn ($r) => $r->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->whereHas('team', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('seasonTeamPlayer.player', fn ($pl) => $pl->where('last_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%")))))
                // Por disputar pero ya programada (D32): sale con su fecha y sede.
                ->when($term === '', fn ($q) => $q->orWhere(fn ($w) => $this->pendingScheduled($w))))
            ->with([
                'season:id,sport_id,name',
                'season.sport:id,name',
                'group:id,name',
                'venue:id,name',
                'results' => fn ($r) => $r->where('place', '<=', 3),
                'results.team:id,name',
                'results.seasonTeamPlayer.player:id,first_name,last_name',
            ])
            ->orderBy(Season::query()->join('sports', 'sports.id', '=', 'seasons.sport_id')
                ->select('sports.name')->whereColumn('seasons.id', 'season_stages.season_id'))
            ->orderBy(Season::query()->select('name')->whereColumn('seasons.id', 'season_stages.season_id'))
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Programacion completa (D32): los partidos por jugar de matchesOf() y
     * las fases de podio directo programadas y aun sin podio, en un solo
     * orden por fecha para paginarlas juntas. Cada fila trae kind ('match'
     * o 'stage'), id y scheduled_at. Con busqueda solo quedan partidos: la
     * busqueda mira delegaciones y una prueba sin podio aun no tiene.
     *
     * @param  array{series?: int|string|null, sportId?: ?int, seasonId?: ?int, search?: string}  $filters
     */
    public function scheduleOf(int $gamesEditionId, array $filters = []): QueryBuilder
    {
        $matches = $this->matchesOf($gamesEditionId, false, $filters)->reorder()->toBase()
            ->selectRaw("'match' AS kind, matches.id, matches.scheduled_at");

        $stages = $this->scheduledPodiumsOf($gamesEditionId, $filters)
            ->when(trim($filters['search'] ?? '') !== '', fn ($q) => $q->whereRaw('1 = 0'))
            ->toBase()
            ->selectRaw("'stage' AS kind, season_stages.id, season_stages.scheduled_at");

        return DB::query()->fromSub($matches->unionAll($stages), 'schedule')
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderBy('kind')
            ->orderBy('id');
    }

    /**
     * Fases de podio directo por disputar y ya programadas (D32).
     *
     * @param  array{series?: int|string|null, sportId?: ?int, seasonId?: ?int}  $filters
     * @return Builder<SeasonStage>
     */
    public function scheduledPodiumsOf(int $gamesEditionId, array $filters = []): Builder
    {
        return $this->pendingScheduled($this->stageInSeries(SeasonStage::query(), $filters['series'] ?? null)
            ->where('mode', StageMode::DirectPodium->value)
            ->whereIn('season_id', $this->visibleSeasonIds($gamesEditionId, $filters)));
    }

    /** Series de la olimpiada con sus delegaciones inscritas. */
    public function seriesOf(int $gamesEditionId): Collection
    {
        return GamesEditionGroup::query()
            ->where('games_edition_id', $gamesEditionId)
            ->with(['editionTeams' => fn ($q) => $q
                ->where('status', EditionTeamStatus::Registered->value)
                ->with('team:id,name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'games_edition_id', 'name', 'sort_order']);
    }

    /**
     * Cuanto hay en cada serie, para sus tarjetas: partidos por jugar,
     * jugados, los de hoy, el proximo y los podios. La clave es el id de la
     * serie (como texto) o FINALS; una serie sin nada no aparece.
     *
     * @return array<string, array{pending: int, played: int, today: int, nextAt: ?Carbon, podiums: int, podiumStages: int}>
     */
    public function seriesSummary(int $gamesEditionId): array
    {
        $pending = MatchStatus::Pending->value;
        $today = now()->startOfDay();
        $empty = ['pending' => 0, 'played' => 0, 'today' => 0, 'nextAt' => null, 'podiums' => 0, 'podiumStages' => 0];
        $summary = [];

        $matches = GameMatch::query()
            ->leftJoin('season_stages', 'season_stages.id', '=', 'matches.season_stage_id')
            ->whereIn('matches.season_id', $this->visibleSeasonIds($gamesEditionId))
            ->whereIn('matches.status', [$pending, MatchStatus::Played->value, MatchStatus::Walkover->value])
            ->selectRaw('season_stages.group_id AS series_id')
            ->selectRaw('SUM(matches.status = ?) AS pending', [$pending])
            ->selectRaw('SUM(matches.status <> ?) AS played', [$pending])
            ->selectRaw('SUM(matches.status = ? AND matches.scheduled_at >= ? AND matches.scheduled_at < ?) AS today',
                [$pending, $today->toDateTimeString(), $today->copy()->addDay()->toDateTimeString()])
            ->selectRaw('MIN(CASE WHEN matches.status = ? AND matches.scheduled_at >= ? THEN matches.scheduled_at END) AS next_at',
                [$pending, $today->toDateTimeString()])
            ->groupBy('season_stages.group_id')
            ->toBase()
            ->get();

        foreach ($matches as $row) {
            $summary[$this->seriesKey($row->series_id)] = [
                'pending' => (int) $row->pending,
                'played' => (int) $row->played,
                'today' => (int) $row->today,
                'nextAt' => $row->next_at ? Carbon::parse($row->next_at) : null,
            ] + $empty;
        }

        $podiums = SeasonStage::query()
            ->where('mode', StageMode::DirectPodium->value)
            ->whereIn('season_id', $this->visibleSeasonIds($gamesEditionId))
            ->selectRaw('group_id, COUNT(*) AS stages')
            ->selectRaw('SUM(EXISTS (SELECT 1 FROM season_stage_results r WHERE r.season_stage_id = season_stages.id)) AS decided')
            ->groupBy('group_id')
            ->toBase()
            ->get();

        foreach ($podiums as $row) {
            $key = $this->seriesKey($row->group_id);
            $summary[$key] = ['podiums' => (int) $row->decided, 'podiumStages' => (int) $row->stages] + ($summary[$key] ?? $empty);
        }

        // Las pruebas de podio programadas cuentan como "por jugar" (D32).
        $scheduled = $this->scheduledPodiumsOf($gamesEditionId)
            ->selectRaw('group_id, COUNT(*) AS pending')
            ->selectRaw('SUM(scheduled_at >= ? AND scheduled_at < ?) AS today',
                [$today->toDateTimeString(), $today->copy()->addDay()->toDateTimeString()])
            ->selectRaw('MIN(CASE WHEN scheduled_at >= ? THEN scheduled_at END) AS next_at', [$today->toDateTimeString()])
            ->groupBy('group_id')
            ->toBase()
            ->get();

        foreach ($scheduled as $row) {
            $key = $this->seriesKey($row->group_id);
            $card = $summary[$key] ?? $empty;
            $next = $row->next_at ? Carbon::parse($row->next_at) : null;

            $summary[$key] = [
                'pending' => $card['pending'] + (int) $row->pending,
                'today' => $card['today'] + (int) $row->today,
                'nextAt' => $card['nextAt'] && (! $next || $card['nextAt']->lte($next)) ? $card['nextAt'] : $next,
            ] + $card;
        }

        return $summary;
    }

    /**
     * Competencias publicas con algo que mostrar en una serie (partidos no
     * cancelados o un podio directo). Alimentan el filtro por deporte.
     */
    public function seasonsIn(int $gamesEditionId, int|string|null $series): Collection
    {
        return $this->visibleSeasons()
            ->with('sport:id,name')
            ->where('games_edition_id', $gamesEditionId)
            ->where(fn ($q) => $q
                ->whereHas('matches', fn ($m) => $this->matchInSeries(
                    $m->where('status', '!=', MatchStatus::Canceled->value), $series))
                ->orWhereHas('stages', fn ($s) => $this->stageInSeries(
                    $s->where('mode', StageMode::DirectPodium->value), $series)))
            ->orderBy('name')
            ->get(['id', 'games_edition_id', 'sport_id', 'name', 'status']);
    }

    /** Una competencia solo si es publica (los ids vienen del navegador). */
    public function findVisibleSeason(?int $seasonId): ?Season
    {
        return $seasonId ? $this->visibleSeasons()->with('gamesEdition', 'sport')->find($seasonId) : null;
    }

    /** Ids de las competencias publicas de la olimpiada, acotadas por deporte o competencia. */
    private function visibleSeasonIds(int $gamesEditionId, array $filters = []): Builder
    {
        return $this->visibleSeasons()
            ->where('games_edition_id', $gamesEditionId)
            ->when($filters['sportId'] ?? null, fn ($q, int $id) => $q->where('sport_id', $id))
            ->when($filters['seasonId'] ?? null, fn ($q, int $id) => $q->where('seasons.id', $id))
            ->select('seasons.id');
    }

    /** Partidos de una serie; FINALS = sin fase o de una fase sin serie; null = todos. */
    private function matchInSeries(Builder|Relation $query, int|string|null $series): Builder|Relation
    {
        return match (true) {
            $series === null => $query,
            $series === self::FINALS => $query->where(fn ($q) => $q
                ->whereNull('season_stage_id')
                ->orWhereHas('stage', fn ($s) => $s->whereNull('group_id'))),
            default => $query->whereHas('stage', fn ($s) => $s->where('group_id', (int) $series)),
        };
    }

    /** Fases de una serie; FINALS = fases sin serie; null = todas. */
    private function stageInSeries(Builder|Relation $query, int|string|null $series): Builder|Relation
    {
        return match (true) {
            $series === null => $query,
            $series === self::FINALS => $query->whereNull('group_id'),
            default => $query->where('group_id', (int) $series),
        };
    }

    /** Sin podio registrado y con fecha o sede: una prueba ya programada. */
    private function pendingScheduled(Builder $query): Builder
    {
        return $query->doesntHave('results')
            ->where(fn ($s) => $s->whereNotNull('scheduled_at')->orWhereNotNull('venue_id'));
    }

    private function seriesKey(mixed $groupId): string
    {
        return $groupId === null ? self::FINALS : (string) $groupId;
    }
}
