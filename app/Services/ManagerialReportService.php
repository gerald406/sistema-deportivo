<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchModality;
use App\Enums\MatchStatus;
use App\Enums\SportFormatType;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\MatchEvent;
use App\Models\Season;
use App\Models\SeasonTeamPlayer;
use App\Models\Suspension;
use App\Support\ParticipantLabel;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Reporte Gerencial de una temporada. Equivalente a
 * pichanga ReportService::generateManagerialPdf() sin ramas por deporte:
 * lo que cambia entre deportes se decide por el FORMATO efectivo de los
 * partidos (discipline.format_type ?? sport.format_type, el mismo criterio
 * que Partidos usa para MatchPeriod vs EventLineup: GameMatchService).
 *
 * No autoriza: ManagerialReportController lo hace con
 * SeasonPolicy::printReports antes de llamar aqui.
 */
class ManagerialReportService
{
    private const DECIDED = [MatchStatus::Played, MatchStatus::Walkover];

    public function __construct(
        private GameMatchService $matches,
        private StandingService $standings,
    ) {
    }

    /**
     * @return array<string, mixed> datos para reports.pdf.managerial
     */
    public function collect(int $seasonId): array
    {
        $season = Season::with('tournament.organizer', 'sport')->findOrFail($seasonId);

        $lastMatchday = $this->lastCompletedMatchday($season);
        $nextMatchday = $this->nextMatchday($season, $lastMatchday);
        $hasHeadToHead = $this->hasHeadToHead($season);

        return [
            'season' => $season,
            'tournament' => $season->tournament,
            'organizer' => $season->tournament->organizer,
            'scoring' => $season->scoringConfigs()->orderBy('id')->first(),
            'generatedAt' => now()->format('d/m/Y H:i'),
            'lastMatchday' => $lastMatchday,
            'lastResults' => $lastMatchday ? $this->matchRows($lastMatchday, onlyDecided: true) : collect(),
            'nextMatchday' => $nextMatchday,
            'nextGames' => $nextMatchday ? $this->matchRows($nextMatchday, onlyDecided: false) : collect(),
            // La tabla solo existe para enfrentamientos (individual/postas van al medallero).
            'showStandings' => $hasHeadToHead,
            // Ya calculada en ResultService::afterResultChanged(): solo se lee.
            'standings' => $hasHeadToHead ? $this->standings->tableFor($season) : collect(),
            'scoreUnit' => $this->usesSets($season) ? 'sets' : 'goals',
            'showScorers' => $hasHeadToHead && $season->sport->eventTypes()->where('code', 'goal')->exists(),
            'topScorers' => $this->topScorers($season),
            'suspensions' => $this->activeSuspensions($season),
            'yellowCardRisks' => $this->playersWithTwoYellows($season),
        ];
    }

    public function pdf(int $seasonId): DomPdf
    {
        return Pdf::loadView('reports.pdf.managerial', $this->collect($seasonId))->setPaper('A4', 'landscape');
    }

    public function filename(int $seasonId): string
    {
        $season = Season::with('tournament:id,name')->findOrFail($seasonId);

        return 'Reporte_Gerencial_' . str($season->tournament->name . ' ' . $season->name)->slug('_') . '.pdf';
    }

    /**
     * Ultima jornada completada. matchdays.is_completed se marca a mano y
     * en la practica queda en false aunque todos sus partidos esten
     * jugados, asi que tambien cuenta como completada la jornada que tiene
     * partidos y ninguno sigue Programado.
     */
    private function lastCompletedMatchday(Season $season): ?Matchday
    {
        return $season->matchdays()
            ->where(fn ($q) => $q->where('is_completed', true)
                ->orWhere(fn ($q) => $q->has('matches')
                    ->whereDoesntHave('matches', fn ($m) => $m->where('status', MatchStatus::Pending->value))))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    /** Primera jornada (por fecha) no completada con partidos programados, posterior a la ultima completada. */
    private function nextMatchday(Season $season, ?Matchday $last): ?Matchday
    {
        return $season->matchdays()
            ->where('is_completed', false)
            ->whereHas('matches', fn ($m) => $m->where('status', MatchStatus::Pending->value))
            ->when($last, fn ($q) => $q->whereKeyNot($last->id))
            ->orderByRaw('start_date IS NULL, start_date')
            ->orderBy('id')
            ->first();
    }

    /**
     * Filas listas para la tabla del PDF. Enfrentamiento: local / marcador /
     * visitante. Individual o postas: nombre de la prueba y el ganador
     * (puesto 1), porque no hay "local" ni "visitante".
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function matchRows(Matchday $matchday, bool $onlyDecided): Collection
    {
        return $matchday->matches()
            ->with(['season.sport', 'discipline', 'venue:id,name', 'participants' => ParticipantLabel::eagerLoad()])
            ->when($onlyDecided, fn ($q) => $q->whereIn('status', array_map(fn ($s) => $s->value, self::DECIDED)))
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
                    'isHeadToHead' => $isHeadToHead && $match->participants->count() === 2,
                    'home' => $home ? ParticipantLabel::for($home) : '—',
                    'away' => $away ? ParticipantLabel::for($away) : '—',
                    'homeScore' => $this->score($home),
                    'awayScore' => $this->score($away),
                    'event' => $match->discipline?->name ?? $match->season->sport->name,
                    'participantsCount' => $match->participants->count(),
                    'winner' => $winner ? ParticipantLabel::for($winner) : null,
                    'status' => $match->status,
                    'venue' => $match->venue?->name,
                    'scheduledAt' => $match->scheduled_at,
                ];
            });
    }

    private function score(?EventParticipant $p): ?string
    {
        if ($p === null || $p->result_value === null) {
            return null;
        }

        // decimal:4 -> "3.0000": se muestra sin ceros de relleno.
        return rtrim(rtrim((string) $p->result_value, '0'), '.');
    }

    private function hasHeadToHead(Season $season): bool
    {
        $seasonMatches = $season->matches();

        return $seasonMatches->exists()
            ? $this->matches->queryByFormat(SportFormatType::HeadToHead)->where('season_id', $season->id)->exists()
            : $season->sport->format_type === SportFormatType::HeadToHead;
    }

    /** Sets si los enfrentamientos se juegan al mejor de N (mismo criterio que ResultService::isBestOf). */
    private function usesSets(Season $season): bool
    {
        return $season->matches()
            ->whereIn('modality', [MatchModality::BestOf1->value, MatchModality::BestOf3->value, MatchModality::BestOf5->value])
            ->exists();
    }

    /** Top 20 por goles (match_events con event_type 'goal') en partidos jugados de la temporada. */
    private function topScorers(Season $season): Collection
    {
        return $this->playerEventCounts($season, 'goal')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row) => $this->withRosterEntry($row));
    }

    /**
     * Jugadores con 2+ amarillas en la temporada. En este sistema las
     * amarillas NO se acumulan (la doble amarilla en un partido es su
     * propio evento red_card_indirect), asi que es una alerta, no una
     * sancion: "Suspendido" solo si ya tiene una sancion pendiente.
     */
    private function playersWithTwoYellows(Season $season): Collection
    {
        $pendingIds = Suspension::where('season_id', $season->id)->where('is_served', false)->pluck('season_team_player_id')->all();

        return $this->playerEventCounts($season, 'yellow_card')
            ->havingRaw('COUNT(match_events.id) >= 2')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => $this->withRosterEntry($row) + ['suspended' => in_array((int) $row->season_team_player_id, $pendingIds, true)]);
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
            'entry' => SeasonTeamPlayer::with('player:id,first_name,last_name', 'seasonTeam.team:id,name')->find($row->season_team_player_id),
            'total' => (int) $row->total,
        ];
    }

    private function activeSuspensions(Season $season): Collection
    {
        return Suspension::where('season_id', $season->id)
            ->where('is_served', false)
            ->with(['seasonTeamPlayer.player:id,first_name,last_name', 'seasonTeamPlayer.seasonTeam.team:id,name', 'match.matchday:id,name'])
            ->orderByDesc('created_at')
            ->get();
    }
}
