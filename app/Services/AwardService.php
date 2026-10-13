<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EditionTeamStatus;
use App\Enums\MatchStatus;
use App\Enums\RoundType;
use App\Enums\SportFormatType;
use App\Enums\StageMode;
use App\Models\EditionAward;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\GamesEditionMedal;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Puntaje general de la olimpiada (Art. 28 y cuadro de la pag. 9 de las
 * bases): cada podio suma a su delegacion los puntos de su fila del cuadro.
 *
 * Todo es DERIVADO y reconstruible: rebuild() borra y recalcula
 * edition_awards y games_edition_medals de la olimpiada. Se llama de forma
 * sincrona cada vez que cambia algo de lo que depende (un resultado, un
 * podio, una fase, los puntos de una fila, una competencia), dentro de la
 * misma transaccion, igual que StandingService.
 *
 * De donde sale el podio de una competencia activa:
 *   - Con fases, de su ULTIMA fase (la final de una serie clasifica, no
 *     premia):
 *       · podio directo: las filas registradas;
 *       · partidos: 1.o y 2.o, ganador y perdedor de la final; 3.o, el
 *         ganador del partido por el tercer puesto (Art. 10: se define
 *         jugando). Sin ese partido no hay 3.o, salvo en una llave de 3,
 *         donde el tercero es quien perdio la unica semifinal.
 *   - Sin fases: ranking/postas, los puestos 1 a 3 de sus pruebas jugadas
 *     de ronda final o regular; enfrentamiento, ganador y perdedor de su
 *     partido final.
 *
 * Puntos = los de la fila del cuadro para ese puesto; 0 si la competencia
 * no tiene fila o la fila no tiene puntos (D6): el podio cuenta igual.
 * Un puesto compartido da el puesto y los puntos completos a cada uno.
 */
class AwardService
{
    public function __construct(private GameMatchService $matches)
    {
    }

    public function rebuild(GamesEdition $edition): void
    {
        DB::transaction(function () use ($edition) {
            EditionAward::where('games_edition_id', $edition->id)->delete();
            GamesEditionMedal::where('games_edition_id', $edition->id)->delete();

            $seasons = $edition->seasons()->where('is_active', true)
                ->with(['event', 'sport', 'discipline', 'stages'])
                ->get();

            $totals = []; // team_id => [oro, plata, bronce, puntos]
            $now = now();
            $rows = [];

            foreach ($seasons as $season) {
                foreach ($this->podiumOf($season) as [$place, $teamId]) {
                    $points = $season->event?->pointsFor($place) ?? 0;

                    $rows[] = [
                        'games_edition_id' => $edition->id,
                        'season_id' => $season->id,
                        'games_edition_event_id' => $season->games_edition_event_id,
                        'team_id' => $teamId,
                        'place' => $place,
                        'points' => $points,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $totals[$teamId] ??= [0, 0, 0, 0];
                    $totals[$teamId][$place - 1]++;
                    $totals[$teamId][3] += $points;
                }
            }

            EditionAward::insert($rows);

            foreach ($totals as $teamId => [$gold, $silver, $bronze, $points]) {
                GamesEditionMedal::create([
                    'games_edition_id' => $edition->id,
                    'team_id' => $teamId,
                    'gold' => $gold,
                    'silver' => $silver,
                    'bronze' => $bronze,
                    'total_points' => $points,
                ]);
            }
        });
    }

    /**
     * Clasificacion general: una fila por delegacion inscrita en la
     * olimpiada (tambien las que aun no suman). Orden: puntos, oros,
     * platas, bronces y nombre; valores iguales comparten posicion (S4).
     * Una delegacion descalificada conserva sus premios pero va al final
     * y sin posicion (S5).
     *
     * @return Collection<int, object{position: ?int, team: Team, group: ?\App\Models\GamesEditionGroup, gold: int, silver: int, bronze: int, total_points: int, disqualified: bool, status: EditionTeamStatus}>
     */
    public function table(GamesEdition $edition): Collection
    {
        $medals = GamesEditionMedal::where('games_edition_id', $edition->id)->get()->keyBy('team_id');

        $rows = $edition->editionTeams()->with(['team:id,name,logo_path', 'group:id,name'])->get()
            ->map(function ($entry) use ($medals) {
                $medal = $medals->get($entry->team_id);

                return (object) [
                    'position' => null,
                    'team' => $entry->team,
                    'group' => $entry->group,
                    'gold' => (int) ($medal->gold ?? 0),
                    'silver' => (int) ($medal->silver ?? 0),
                    'bronze' => (int) ($medal->bronze ?? 0),
                    'total_points' => (int) ($medal->total_points ?? 0),
                    'disqualified' => $entry->status === EditionTeamStatus::Disqualified,
                    'status' => $entry->status,
                ];
            })
            ->sortBy([
                fn ($a, $b) => $a->disqualified <=> $b->disqualified,
                fn ($a, $b) => $b->total_points <=> $a->total_points,
                fn ($a, $b) => $b->gold <=> $a->gold,
                fn ($a, $b) => $b->silver <=> $a->silver,
                fn ($a, $b) => $b->bronze <=> $a->bronze,
                fn ($a, $b) => strcasecmp($a->team->name, $b->team->name),
            ])
            ->values();

        $previous = null;

        foreach ($rows as $index => $row) {
            if ($row->disqualified) {
                continue;
            }

            $key = [$row->total_points, $row->gold, $row->silver, $row->bronze];
            $row->position = $previous !== null && $previous[0] === $key ? $previous[1] : $index + 1;
            $previous = [$key, $row->position];
        }

        return $rows;
    }

    /**
     * Premios de una delegacion en la olimpiada: competencia, puesto y puntos.
     *
     * @return Collection<int, EditionAward>
     */
    public function detail(GamesEdition $edition, Team $team): Collection
    {
        return EditionAward::with('season:id,name')
            ->where('games_edition_id', $edition->id)
            ->where('team_id', $team->id)
            ->get()
            ->sortBy([['place', 'asc'], fn ($a, $b) => strcasecmp($a->season->name, $b->season->name)])
            ->values();
    }

    // ------------------------------------------------------------------

    /**
     * @return list<array{0: int, 1: int}> [puesto, team_id]
     */
    private function podiumOf(Season $season): array
    {
        /** @var SeasonStage|null $last */
        $last = $season->stages->last();

        if ($last === null) {
            return $this->podiumFromLooseMatches($season);
        }

        return $last->mode === StageMode::DirectPodium
            ? $last->results()->get(['place', 'team_id'])->map(fn ($r) => [$r->place, $r->team_id])->all()
            : $this->podiumFromBracket($last);
    }

    /** @return list<array{0: int, 1: int}> */
    private function podiumFromBracket(SeasonStage $stage): array
    {
        $closed = $stage->matches()
            ->whereIn('status', [MatchStatus::Played->value, MatchStatus::Walkover->value])
            ->tap($this->withParticipantTeams())
            ->get();

        $final = $closed->firstWhere('round_type', RoundType::Final);

        if ($final === null) {
            return [];
        }

        $podium = array_merge($this->placed($final, 1, 1), $this->placed($final, 2, 2));

        $third = $closed->firstWhere('round_type', RoundType::ThirdPlace);

        if ($third !== null) {
            return array_merge($podium, $this->placed($third, 1, 3));
        }

        // Llave de 3: una sola semifinal y ningun partido por el tercer
        // puesto; el tercero es quien la perdio.
        $semis = $stage->matches()->where('round_type', RoundType::SemiFinal->value)->count();
        $hasThirdPlaceMatch = $stage->matches()->where('round_type', RoundType::ThirdPlace->value)->exists();
        $semi = $closed->firstWhere('round_type', RoundType::SemiFinal);

        return $semis === 1 && ! $hasThirdPlaceMatch && $semi !== null
            ? array_merge($podium, $this->placed($semi, 2, 3))
            : $podium;
    }

    /** @return list<array{0: int, 1: int}> */
    private function podiumFromLooseMatches(Season $season): array
    {
        $matches = GameMatch::where('season_id', $season->id)
            ->whereNull('season_stage_id')
            ->whereIn('status', [MatchStatus::Played->value, MatchStatus::Walkover->value])
            ->whereIn('round_type', [RoundType::Final->value, RoundType::Regular->value])
            ->with('discipline')
            ->tap($this->withParticipantTeams())
            ->get();

        $podium = [];

        foreach ($matches as $match) {
            if ($this->matches->formatFor($season, $match->discipline) === SportFormatType::HeadToHead) {
                // Solo la final, y solo si tuvo ganador y perdedor.
                if ($match->round_type === RoundType::Final && $this->placed($match, 1, 1) !== [] && $this->placed($match, 2, 2) !== []) {
                    $podium = array_merge($podium, $this->placed($match, 1, 1), $this->placed($match, 2, 2));
                }

                continue;
            }

            foreach ([1, 2, 3] as $place) {
                $podium = array_merge($podium, $this->placed($match, $place, $place));
            }
        }

        return $podium;
    }

    /**
     * Delegaciones de los participantes de $match con ese puesto.
     *
     * @return list<array{0: int, 1: int}> [[$place, team_id], ...]
     */
    private function placed(GameMatch $match, int $position, int $place): array
    {
        return $match->participants
            ->where('position', $position)
            ->map(fn (EventParticipant $p) => $this->teamOf($p))
            ->filter()
            ->map(fn (int $teamId) => [$place, $teamId])
            ->values()->all();
    }

    private function withParticipantTeams(): \Closure
    {
        return fn ($query) => $query->with(['participants.participant' => fn (MorphTo $m) => $m->morphWith([
            SeasonTeamPlayer::class => ['seasonTeam:id,team_id'],
        ])]);
    }

    /** La delegacion del premio: la del plantel del participante, o la delegacion misma. */
    private function teamOf(EventParticipant $p): ?int
    {
        $model = $p->participant;

        return match (true) {
            $model instanceof SeasonTeam => $model->team_id,
            $model instanceof SeasonTeamPlayer => $model->seasonTeam?->team_id,
            default => null,
        };
    }
}
