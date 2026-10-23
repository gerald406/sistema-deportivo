<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchModality;
use App\Enums\MatchStatus;
use App\Enums\RoundType;
use App\Enums\SportFormatType;
use App\Models\EventParticipant;
use App\Models\EventType;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\MatchEvent;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Suspension;
use App\Support\ParticipantLabel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Resumen de una competencia: que secciones tienen sentido y sus datos.
 * UNICA fuente de verdad para la zona publica (Public\PublicResults) y el
 * Reporte Gerencial (ManagerialReportService): ambos muestran lo mismo con
 * el mismo criterio.
 *
 * CRITERIO DE SECCIONES (por competencia, desde los datos; nunca por nombre
 * de deporte ni un booleano "futbol/voley"):
 *   - Posiciones: la competencia tiene enfrentamientos (formato efectivo
 *     discipline.format_type ?? sport.format_type = head_to_head, el mismo
 *     de Partidos). Individual y postas alimentan el medallero, no la tabla.
 *   - Goleadores: hay enfrentamientos Y el deporte define el tipo de
 *     incidencia 'goal' (futbol si; voley, ajedrez y atletismo no).
 *   - Amarillas: el deporte define 'yellow_card'.
 *   - Suspendidos: el deporte define alguna incidencia que genera sancion
 *     automatica (SuspensionService::AUTO_CODES) O la competencia ya tiene
 *     sanciones registradas (las manuales existen en cualquier deporte,
 *     p. ej. atletismo).
 */
class SeasonOverviewService
{
    /** @var array<int, array<int, string>> codigos de event_types por deporte (memo del request) */
    private array $eventCodes = [];

    public function __construct(
        private GameMatchService $matches,
        private StandingService $standings,
    ) {
    }

    // ── Capacidades ──────────────────────────────────────────────────────

    /**
     * @return array{standings: bool, scorers: bool, yellows: bool, suspensions: bool}
     */
    public function sections(Season $season): array
    {
        $headToHead = $this->hasHeadToHead($season);
        $codes = $this->eventCodes($season);

        return [
            'standings' => $headToHead,
            'scorers' => $headToHead && in_array('goal', $codes, true),
            'yellows' => in_array('yellow_card', $codes, true),
            'suspensions' => array_intersect(SuspensionService::AUTO_CODES, $codes) !== []
                || Suspension::where('season_id', $season->id)->exists(),
        ];
    }

    public function hasHeadToHead(Season $season): bool
    {
        return $season->matches()->exists()
            ? $this->matches->queryByFormat(SportFormatType::HeadToHead)->where('season_id', $season->id)->exists()
            : $season->sport->format_type === SportFormatType::HeadToHead;
    }

    /** Sets si los enfrentamientos se juegan al mejor de N (criterio de ResultService::isBestOf). */
    public function usesSets(Season $season): bool
    {
        return $season->matches()
            ->whereIn('modality', [MatchModality::BestOf1->value, MatchModality::BestOf3->value, MatchModality::BestOf5->value])
            ->exists();
    }

    /**
     * Columnas de la tabla segun los datos de SeasonStanding, no un
     * hardcode por deporte: PE solo si hay empates posibles (no se juega
     * por sets) o registrados; a favor/en contra/diferencia solo si
     * extra_stats los trae, rotulados como goles o sets.
     *
     * @param  Collection<int, \App\Models\SeasonStanding>  $table
     * @return array<int, array{key: string, label: string, title: string}>
     */
    public function standingColumns(Season $season, Collection $table): array
    {
        $sets = $this->usesSets($season);
        $has = fn (string $k) => $table->contains(fn ($row) => array_key_exists($k, $row->extra_stats ?? []));

        $columns = [
            ['key' => 'matches_played', 'label' => 'PJ', 'title' => 'Partidos jugados'],
            ['key' => 'wins', 'label' => 'PG', 'title' => 'Partidos ganados'],
        ];

        if (! $sets || $table->sum('draws') > 0) {
            $columns[] = ['key' => 'draws', 'label' => 'PE', 'title' => 'Partidos empatados'];
        }

        $columns[] = ['key' => 'losses', 'label' => 'PP', 'title' => 'Partidos perdidos'];
        $columns[] = ['key' => 'walkovers_against', 'label' => 'WO', 'title' => 'Walkovers en contra'];

        foreach ([
            'scored' => $sets ? ['SG', 'Sets ganados'] : ['GF', 'Goles a favor'],
            'conceded' => $sets ? ['SP', 'Sets perdidos'] : ['GC', 'Goles en contra'],
            'diff' => $sets ? ['DS', 'Diferencia de sets'] : ['DG', 'Diferencia de goles'],
        ] as $key => [$label, $title]) {
            if ($has($key)) {
                $columns[] = ['key' => 'extra_stats.' . $key, 'label' => $label, 'title' => $title];
            }
        }

        return $columns;
    }

    // ── Datos ────────────────────────────────────────────────────────────

    /** Tabla ya calculada en ResultService::afterResultChanged(): solo se lee. */
    public function standings(Season $season): Collection
    {
        return $this->standings->tableFor($season);
    }

    /**
     * Jornadas completadas, la mas reciente primero. matchdays.is_completed
     * se marca a mano (y suele quedar en false), asi que tambien cuenta la
     * jornada con partidos y ninguno Programado.
     */
    public function completedMatchdays(Season $season): Collection
    {
        return $season->matchdays()
            ->where(fn ($q) => $q->where('is_completed', true)
                ->orWhere(fn ($q) => $q->has('matches')
                    ->whereDoesntHave('matches', fn ($m) => $m->where('status', MatchStatus::Pending->value))))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /** Primera jornada (por fecha) no completada con partidos programados. */
    public function nextMatchday(Season $season): ?Matchday
    {
        return $season->matchdays()
            ->where('is_completed', false)
            ->whereHas('matches', fn ($m) => $m->where('status', MatchStatus::Pending->value))
            ->orderByRaw('start_date IS NULL, start_date')
            ->orderBy('id')
            ->first();
    }

    /**
     * Filas por partido. Enfrentamiento: local / marcador / visitante.
     * Individual o postas: prueba + ganador (puesto 1).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function matchRows(Matchday $matchday, bool $onlyDecided): Collection
    {
        return $matchday->matches()
            ->with($this->matchRowRelations())
            ->when($onlyDecided, fn ($q) => $q->whereIn('status', [MatchStatus::Played->value, MatchStatus::Walkover->value]))
            ->when(! $onlyDecided, fn ($q) => $q->where('status', '!=', MatchStatus::Canceled->value))
            ->orderByRaw('scheduled_at IS NULL, scheduled_at')
            ->orderBy('id')
            ->get()
            ->map(fn (GameMatch $match) => $this->matchRow($match));
    }

    /** Relaciones que matchRow() lee: se cargan con ->with() para no consultar por fila. */
    public function matchRowRelations(): array
    {
        return ['season.sport', 'discipline', 'venue', 'stage:id,group_id', 'participants' => ParticipantLabel::eagerLoad()];
    }

    /**
     * Un partido o prueba listo para mostrar. Requiere cargadas las
     * relaciones de matchRowRelations().
     *
     * @return array<string, mixed>
     */
    public function matchRow(GameMatch $match): array
    {
        $isHeadToHead = $this->matches->formatFor($match->season, $match->discipline) === SportFormatType::HeadToHead;
        $home = $match->participants->get(0);
        $away = $match->participants->get(1);
        $winner = $match->participants->firstWhere('position', 1);

        return [
            'id' => $match->id,
            'kind' => 'match',
            'stage' => null,
            'isHeadToHead' => $isHeadToHead && $match->participants->count() === 2,
            'home' => $home ? ParticipantLabel::for($home) : '—',
            'away' => $away ? ParticipantLabel::for($away) : '—',
            'homeScore' => $this->score($home),
            'awayScore' => $this->score($away),
            'event' => $match->discipline?->name ?? $match->season->sport->name,
            'participantsCount' => $match->participants->count(),
            'winner' => $winner ? ParticipantLabel::for($winner) : null,
            // Lado ganador de un enfrentamiento (null = empate o sin resultado).
            // En un empate ResultService deja a AMBOS en el puesto 1.
            'winnerSide' => match (true) {
                $winner === null, $match->participants->where('position', 1)->count() !== 1 => null,
                $winner->is($home) => 'home',
                $winner->is($away) => 'away',
                default => null,
            },
            // Desempate de un partido de llave: "Penales 4-3" (sin marcador en el sorteo).
            'tiebreak' => $match->tiebreak_type
                ? trim($match->tiebreak_type->label().' '.($home?->tiebreak_score !== null && $away?->tiebreak_score !== null
                    ? "{$home->tiebreak_score}-{$away->tiebreak_score}" : ''))
                : null,
            // Pruebas individuales y postas: los tres primeros con su marca.
            'podium' => $match->participants->whereNotNull('position')->sortBy('position')->take(3)
                ->map(fn (EventParticipant $p) => ['position' => $p->position, 'name' => ParticipantLabel::for($p), 'mark' => $this->score($p)])
                ->values()->all(),
            'season' => $match->season->name,
            'sport' => $match->season->sport->name,
            'round' => $match->round_type,
            'status' => $match->status,
            'venue' => $match->venue,
            'scheduledAt' => $match->scheduled_at,
            // Delegaciones que juegan (la pagina publica resalta "Mi delegacion") y serie de su fase.
            'teamIds' => $match->participants
                ->map(fn (EventParticipant $p) => match (true) {
                    $p->participant instanceof SeasonTeam => $p->participant->team_id,
                    $p->participant instanceof SeasonTeamPlayer => $p->participant->seasonTeam?->team_id,
                    default => null,
                })
                ->filter()->unique()->values()->all(),
            'seriesId' => $match->stage?->group_id,
        ];
    }

    /**
     * Una fase de podio directo por disputar, con la forma de matchRow()
     * para listarla en la programacion junto a los partidos (D32). Requiere
     * cargadas season.sport, season.discipline y venue.
     *
     * @return array<string, mixed>
     */
    public function stageRow(SeasonStage $stage): array
    {
        return [
            'id' => 'stage-'.$stage->id,
            'kind' => 'stage',
            'stage' => $stage->name,
            'isHeadToHead' => false,
            'home' => '—',
            'away' => '—',
            'homeScore' => null,
            'awayScore' => null,
            'event' => $stage->season->discipline?->name ?? $stage->season->sport->name,
            'participantsCount' => 0,
            'winner' => null,
            'winnerSide' => null,
            'tiebreak' => null,
            'podium' => [],
            'season' => $stage->season->name,
            'sport' => $stage->season->sport->name,
            'round' => RoundType::Regular,
            'status' => MatchStatus::Pending,
            'venue' => $stage->venue,
            'scheduledAt' => $stage->scheduled_at,
            'teamIds' => [],
            'seriesId' => $stage->group_id,
        ];
    }

    public function topScorers(Season $season, int $limit): Collection
    {
        return $this->playerEventCounts($season, 'goal')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->withRosterEntry($row));
    }

    /**
     * Participantes con 2+ amarillas. Las amarillas NO se acumulan en este
     * sistema (la doble amarilla en un partido es su propio evento
     * red_card_indirect): es una alerta; 'suspended' = ya tiene una sancion
     * pendiente.
     */
    public function playersWithTwoYellows(Season $season): Collection
    {
        $pendingIds = Suspension::where('season_id', $season->id)->where('is_served', false)->pluck('season_team_player_id')->all();

        return $this->playerEventCounts($season, 'yellow_card')
            ->havingRaw('COUNT(match_events.id) >= 2')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => $this->withRosterEntry($row) + ['suspended' => in_array((int) $row->season_team_player_id, $pendingIds, true)]);
    }

    public function activeSuspensions(Season $season): Collection
    {
        return Suspension::where('season_id', $season->id)
            ->where('is_served', false)
            ->with(['seasonTeamPlayer.player:id,first_name,last_name', 'seasonTeamPlayer.seasonTeam.team:id,name,logo_path', 'match.matchday:id,name'])
            ->orderByDesc('created_at')
            ->get();
    }

    /** @return array<int, string> */
    private function eventCodes(Season $season): array
    {
        return $this->eventCodes[$season->sport_id] ??= EventType::where('sport_id', $season->sport_id)->pluck('code')->all();
    }

    private function score(?EventParticipant $p): ?string
    {
        if ($p === null || $p->result_value === null) {
            return null;
        }

        // decimal:4 -> "3.0000": se muestra sin ceros de relleno.
        return rtrim(rtrim((string) $p->result_value, '0'), '.');
    }

    private function playerEventCounts(Season $season, string $code): Builder
    {
        return MatchEvent::query()
            ->selectRaw('match_events.season_team_player_id, COUNT(match_events.id) as total')
            ->join('event_types', 'event_types.id', '=', 'match_events.event_type_id')
            ->join('matches', 'matches.id', '=', 'match_events.match_id')
            ->where('matches.season_id', $season->id)
            ->where('matches.status', '!=', MatchStatus::Canceled->value)
            ->where('event_types.code', $code)
            ->groupBy('match_events.season_team_player_id')
            ->toBase();
    }

    /** @return array{entry: SeasonTeamPlayer, total: int} */
    private function withRosterEntry(object $row): array
    {
        return [
            'entry' => SeasonTeamPlayer::with('player:id,first_name,last_name', 'seasonTeam.team:id,name,logo_path')->find($row->season_team_player_id),
            'total' => (int) $row->total,
        ];
    }
}
