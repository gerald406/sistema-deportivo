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
use App\Models\Team;
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
 * La serie es un filtro mas: cada partido y cada podio pertenece a la serie
 * de su fase (season_stages.group_id). Lo que no tiene serie (fase final,
 * finales de podio, partidos sin fase) va al grupo FINALS.
 *
 * Filtros (todos opcionales; llegan del navegador y aqui solo acotan dentro
 * de lo visible): series, sportId, disciplineId, categoryId, gender, teamId,
 * date (Y-m-d o NO_DATE), search y, en podios, state (DECIDED o PENDING).
 */
class PublicResultsService
{
    /** Clave del grupo "Finales y podios": todo lo que no es de una serie. */
    public const FINALS = 'final';

    /** Filtro de fecha: lo que aun no tiene fecha. */
    public const NO_DATE = 'sin-fecha';

    /** Estados del filtro de podios: con podio registrado o por disputar. */
    public const DECIDED = 'definido';

    public const PENDING = 'por-disputar';

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
     * Partidos y pruebas publicos de una olimpiada: los por jugar (la
     * programacion los ordena en scheduleOf()) o los resultados (jugados y
     * walkovers, del mas reciente al mas antiguo). Los cancelados no se
     * publican. Los filtros llegan del navegador: aqui solo acotan dentro
     * de las competencias visibles, nunca las amplian.
     *
     * @param  array<string, mixed>  $filters  ver el docblock de la clase
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
            ->when($filters['teamId'] ?? null, fn ($q, int $teamId) => $q->whereHas('participants', fn ($p) => $p->whereHasMorph(
                'participant', [SeasonTeam::class, SeasonTeamPlayer::class],
                fn ($m, string $type) => $type === SeasonTeam::class
                    ? $m->where('team_id', $teamId)
                    : $m->whereHas('seasonTeam', fn ($t) => $t->where('team_id', $teamId)),
            )))
            ->tap(fn ($q) => $this->onDate($q, 'matches.scheduled_at', $filters['date'] ?? null))
            // Sin fecha al final; con fecha, lo ultimo primero.
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at', $played ? 'desc' : 'asc')
            ->orderBy('id', $played ? 'desc' : 'asc');
    }

    /**
     * Podios directos, por deporte y competencia: los registrados (1.º, 2.º
     * y 3.º) y los por disputar ya programados. Mismos filtros que
     * matchesOf() mas state; la busqueda mira la delegacion y el
     * participante, y la delegacion, el podio o, si esta por disputar, su
     * inscripcion en la competencia.
     *
     * @param  array<string, mixed>  $filters  ver el docblock de la clase
     * @return Builder<SeasonStage>
     */
    public function podiumsOf(int $gamesEditionId, array $filters = []): Builder
    {
        $term = trim($filters['search'] ?? '');
        $teamId = $filters['teamId'] ?? null;

        $decided = fn ($q) => $q->whereHas('results', fn ($r) => $r
            ->when($teamId, fn ($q, int $id) => $q->where('team_id', $id))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('team', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                ->orWhereHas('seasonTeamPlayer.player', fn ($pl) => $pl->where('last_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%")))));

        // Por disputar pero ya programada (D32): sale con su fecha y sede. La
        // busqueda mira nombres del podio, que aun no tiene.
        $pending = fn ($q) => $term === ''
            ? $this->forTeam($this->pendingScheduled($q), $teamId)
            : $q->whereRaw('1 = 0');

        return $this->stageInSeries(SeasonStage::query(), $filters['series'] ?? null)
            ->where('mode', StageMode::DirectPodium->value)
            ->whereIn('season_id', $this->visibleSeasonIds($gamesEditionId, $filters))
            ->where(fn ($q) => match ($filters['state'] ?? null) {
                self::DECIDED => $decided($q),
                self::PENDING => $pending($q),
                default => $q->where($decided)->orWhere($pending),
            })
            ->with([
                'season:id,sport_id,discipline_id,category_id,gender,name',
                'season.sport:id,name',
                'season.category:id,name',
                'group:id,name',
                'venue:id,name,address,latitude,longitude',
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
     * orden para paginarlas juntas: los dias de la fecha mas reciente a la
     * mas antigua y, dentro del dia, por hora; sin fecha al final. Cada fila
     * trae kind ('match' o 'stage'), id, season_id y scheduled_at. Con
     * busqueda solo quedan partidos: la busqueda mira delegaciones y una
     * prueba sin podio aun no tiene.
     *
     * @param  array<string, mixed>  $filters  ver el docblock de la clase
     */
    public function scheduleOf(int $gamesEditionId, array $filters = []): QueryBuilder
    {
        $matches = $this->matchesOf($gamesEditionId, false, $filters)->reorder()->toBase()
            ->selectRaw("'match' AS kind, matches.id, matches.season_id, matches.scheduled_at");

        $stages = $this->scheduledPodiumsOf($gamesEditionId, $filters)
            ->when(trim($filters['search'] ?? '') !== '', fn ($q) => $q->whereRaw('1 = 0'))
            ->toBase()
            ->selectRaw("'stage' AS kind, season_stages.id, season_stages.season_id, season_stages.scheduled_at");

        return DB::query()->fromSub($matches->unionAll($stages), 'schedule')
            ->orderByRaw('scheduled_at IS NULL')
            ->orderByRaw('DATE(scheduled_at) DESC')
            ->orderBy('scheduled_at')
            ->orderBy('kind')
            ->orderBy('id');
    }

    /**
     * Fases de podio directo por disputar y ya programadas (D32).
     *
     * @param  array<string, mixed>  $filters  ver el docblock de la clase
     * @return Builder<SeasonStage>
     */
    public function scheduledPodiumsOf(int $gamesEditionId, array $filters = []): Builder
    {
        $stages = $this->pendingScheduled($this->stageInSeries(SeasonStage::query(), $filters['series'] ?? null)
            ->where('mode', StageMode::DirectPodium->value)
            ->whereIn('season_id', $this->visibleSeasonIds($gamesEditionId, $filters)));

        return $this->onDate($this->forTeam($stages, $filters['teamId'] ?? null), 'season_stages.scheduled_at', $filters['date'] ?? null);
    }

    /**
     * Dias con algo en la programacion o en los resultados, y cuantos: la
     * tira de fechas. Mismos filtros menos la fecha; de la fecha mas
     * reciente a la mas antigua y sin fecha (NO_DATE) al final.
     *
     * @param  array<string, mixed>  $filters  ver el docblock de la clase
     * @return Collection<int, array{key: string, date: ?Carbon, count: int}>
     */
    public function daysOf(int $gamesEditionId, bool $played, array $filters = []): Collection
    {
        unset($filters['date']);

        $items = $played
            ? $this->matchesOf($gamesEditionId, true, $filters)->reorder()->toBase()->select('matches.scheduled_at')
            : $this->scheduleOf($gamesEditionId, $filters)->reorder();

        return DB::query()->fromSub($items, 'items')
            ->selectRaw('DATE(scheduled_at) AS day, COUNT(*) AS total')
            ->groupByRaw('DATE(scheduled_at)')
            ->orderByRaw('day IS NULL')
            ->orderByDesc('day')
            ->get()
            ->map(fn (object $row) => [
                'key' => $row->day ?? self::NO_DATE,
                'date' => $row->day ? Carbon::parse($row->day) : null,
                'count' => (int) $row->total,
            ]);
    }

    /**
     * Cuanto hay de cada deporte en una pestaña ('programacion',
     * 'resultados' o 'podios'), para sus fichas. Mismos filtros menos
     * deporte y prueba, que es lo que la ficha elige; en podios, ademas,
     * cuantos ya estan definidos.
     *
     * @param  array<string, mixed>  $filters  ver el docblock de la clase
     * @return array<int, array{total: int, decided: int}> por id de deporte
     */
    public function countsBySport(int $gamesEditionId, string $view, array $filters = []): array
    {
        unset($filters['sportId'], $filters['disciplineId']);

        $items = match ($view) {
            'podios' => $this->podiumsOf($gamesEditionId, $filters)->reorder()->toBase()
                ->select('season_stages.season_id')
                ->selectRaw('EXISTS (SELECT 1 FROM season_stage_results r WHERE r.season_stage_id = season_stages.id) AS decided'),
            'resultados' => $this->matchesOf($gamesEditionId, true, $filters)->reorder()->toBase()
                ->select('matches.season_id')->selectRaw('1 AS decided'),
            default => $this->scheduleOf($gamesEditionId, $filters)->reorder()
                ->select('season_id')->selectRaw('0 AS decided'),
        };

        return DB::query()->fromSub($items, 'items')
            ->join('seasons', 'seasons.id', '=', 'items.season_id')
            ->groupBy('seasons.sport_id')
            ->selectRaw('seasons.sport_id, COUNT(*) AS total, SUM(items.decided) AS decided')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->sport_id => ['total' => (int) $row->total, 'decided' => (int) $row->decided]])
            ->all();
    }

    /**
     * Sugerencias del buscador para $term: delegaciones de la olimpiada,
     * competencias publicas y participantes de sus planteles. Del
     * participante solo salen nombre y delegacion.
     *
     * @return array{teams: Collection, seasons: Collection, players: Collection}
     */
    public function suggestionsFor(int $gamesEditionId, string $term, int $limit = 5): array
    {
        $like = '%'.trim($term).'%';

        return [
            'teams' => Team::query()
                ->whereHas('editionTeams', fn ($q) => $q->where('games_edition_id', $gamesEditionId))
                ->where('name', 'like', $like)
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'name']),
            'seasons' => $this->visibleSeasons()
                ->with('sport:id,name')
                ->where('games_edition_id', $gamesEditionId)
                ->where('seasons.name', 'like', $like)
                ->orderBy('seasons.name')
                ->limit($limit)
                ->get(['id', 'sport_id', 'name']),
            'players' => $this->rosterEntries($gamesEditionId)
                ->whereHas('player', fn ($q) => $q->where(fn ($w) => $w->where('last_name', 'like', $like)->orWhere('first_name', 'like', $like)))
                ->limit(50)
                ->get()
                // Una fila por persona y delegacion aunque juegue varias disciplinas.
                ->unique(fn (SeasonTeamPlayer $entry) => $entry->player_id.'-'.$entry->seasonTeam->team_id)
                ->sortBy(fn (SeasonTeamPlayer $entry) => $entry->player->last_name.' '.$entry->player->first_name)
                ->take($limit)
                ->values(),
        ];
    }

    /** Un participante solo si esta en el plantel de una competencia publica de la olimpiada (el id viene del navegador). */
    public function findRosterEntry(int $gamesEditionId, int $seasonTeamPlayerId): ?SeasonTeamPlayer
    {
        return $this->rosterEntries($gamesEditionId)->find($seasonTeamPlayerId);
    }

    /** Una competencia solo si es publica y de la olimpiada (el id viene del navegador). */
    public function findSeasonIn(int $gamesEditionId, int $seasonId): ?Season
    {
        return $this->visibleSeasons()->where('games_edition_id', $gamesEditionId)->find($seasonId);
    }

    /** Delegaciones inscritas en la olimpiada (cualquier estado): el filtro "Delegación". */
    public function teamsOf(int $gamesEditionId): Collection
    {
        return Team::query()
            ->whereHas('editionTeams', fn ($q) => $q->where('games_edition_id', $gamesEditionId))
            ->orderBy('name')
            ->get(['id', 'name']);
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
     * cancelados o un podio directo). Alimentan las fichas de deporte,
     * prueba, nivel y genero.
     */
    public function seasonsIn(int $gamesEditionId, int|string|null $series): Collection
    {
        return $this->visibleSeasons()
            ->with(['sport:id,name', 'discipline:id,name', 'category:id,name,min_age'])
            ->where('games_edition_id', $gamesEditionId)
            ->where(fn ($q) => $q
                ->whereHas('matches', fn ($m) => $this->matchInSeries(
                    $m->where('status', '!=', MatchStatus::Canceled->value), $series))
                ->orWhereHas('stages', fn ($s) => $this->stageInSeries(
                    $s->where('mode', StageMode::DirectPodium->value), $series)))
            ->orderBy('name')
            ->get(['id', 'games_edition_id', 'sport_id', 'discipline_id', 'category_id', 'gender', 'name', 'status']);
    }

    /** Una competencia solo si es publica (los ids vienen del navegador). */
    public function findVisibleSeason(?int $seasonId): ?Season
    {
        return $seasonId ? $this->visibleSeasons()->with('gamesEdition', 'sport')->find($seasonId) : null;
    }

    /** Ids de las competencias publicas de la olimpiada, acotadas por deporte, prueba, nivel o genero. */
    private function visibleSeasonIds(int $gamesEditionId, array $filters = []): Builder
    {
        return $this->visibleSeasons()
            ->where('games_edition_id', $gamesEditionId)
            ->when($filters['sportId'] ?? null, fn ($q, int $id) => $q->where('sport_id', $id))
            ->when($filters['disciplineId'] ?? null, fn ($q, int $id) => $q->where('discipline_id', $id))
            ->when($filters['categoryId'] ?? null, fn ($q, int $id) => $q->where('category_id', $id))
            ->when($filters['gender'] ?? null, fn ($q, string $gender) => $q->where('seasons.gender', $gender))
            ->select('seasons.id');
    }

    /** Planteles de las competencias publicas de la olimpiada, con lo que el buscador muestra. */
    private function rosterEntries(int $gamesEditionId): Builder
    {
        return SeasonTeamPlayer::query()
            ->whereHas('seasonTeam', fn ($q) => $q->whereIn('season_id', $this->visibleSeasonIds($gamesEditionId)))
            ->with(['player:id,first_name,last_name', 'seasonTeam:id,team_id', 'seasonTeam.team:id,name']);
    }

    /** Un dia (Y-m-d) o NO_DATE; cualquier otro valor no filtra. */
    private function onDate(Builder $query, string $column, ?string $date): Builder
    {
        return match (true) {
            $date === self::NO_DATE => $query->whereNull($column),
            $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 => $query->whereDate($column, $date),
            default => $query,
        };
    }

    /** Prueba por disputar de una delegacion: la tiene inscrita (y activa) en su competencia. */
    private function forTeam(Builder $query, ?int $teamId): Builder
    {
        return $query->when($teamId, fn ($q, int $id) => $q->whereHas('season.seasonTeams', fn ($t) => $t
            ->where('team_id', $id)
            ->where('is_active', true)));
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
