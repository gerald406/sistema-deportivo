<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchModality;
use App\Enums\MatchStatus;
use App\Enums\SportFormatType;
use App\Models\EventParticipant;
use App\Models\EventType;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\MatchEvent;
use App\Models\Season;
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
            ->with(['season.sport', 'discipline', 'venue', 'participants' => ParticipantLabel::eagerLoad()])
            ->when($onlyDecided, fn ($q) => $q->whereIn('status', [MatchStatus::Played->value, MatchStatus::Walkover->value]))
            ->when(! $onlyDecided, fn ($q) => $q->where('status', '!=', MatchStatus::Canceled->value))
            ->orderByRaw('scheduled_at IS NULL, scheduled_at')
            ->orderBy('id')
            ->get()
            ->map(function (GameMatch $match) {
                $isHeadToHead = $this->matches->formatFor($match->season, $match->discipline) === SportFormatType::HeadToHead;
                $home = $match->participants->get(0);
                $away = $match->participants->get(1);
                $winner = $match->participants->firstWhere('position', 1);

                return [
                    'id' => $match->id,
                    'isHeadToHead' => $isHeadToHead && $match->participants->count() === 2,
                    'home' => $home ? ParticipantLabel::for($home) : '—',
                    'away' => $away ? ParticipantLabel::for($away) : '—',
                    'homeScore' => $this->score($home),
                    'awayScore' => $this->score($away),
                    'event' => $match->discipline?->name ?? $match->season->sport->name,
                    'participantsCount' => $match->participants->count(),
                    'winner' => $winner ? ParticipantLabel::for($winner) : null,
                    'status' => $match->status,
                    'venue' => $match->venue,
                    'scheduledAt' => $match->scheduled_at,
                ];
            });
    }

    /** Goleadores (event_type 'goal') en partidos no cancelados de la competencia. */
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
