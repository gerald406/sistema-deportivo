<?php

declare(strict_types=1);

namespace Tests\Feature\Scores;

use App\Enums\RoundType;
use App\Enums\StageMode;
use App\Livewire\Public\PublicResults;
use App\Livewire\Reports\GeneralScore;
use App\Models\Category;
use App\Models\EditionAward;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\GamesEditionMedal;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\SeasonTeam;
use App\Models\Sport;
use App\Models\Team;
use App\Services\AwardService;
use App\Services\DirectPodiumService;
use App\Services\EditionEventService;
use App\Services\EditionTeamService;
use App\Services\ResultService;
use App\Services\SeasonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\Concerns\BuildsBracket;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * F6 (Art. 28 y cuadro de la pag. 9; S4, S5; focos 5 y 6 del plan maestro):
 * puntaje general derivado de los podios y del cuadro de puntaje.
 */
class AwardServiceTest extends TestCase
{
    use BuildsBracket, BuildsCompetition, CreatesActors, RefreshDatabase;

    /** @var Collection<int, Team> */
    private Collection $teams;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootCompetition();
        $this->teams = collect(range(1, 5))->map(function (int $i) {
            $team = Team::factory()->create(['name' => "Delegación {$i}"]);
            $this->registerDelegation($this->edition, $team);

            return $team;
        });
    }

    /** Competencia con su fila del cuadro (puntos 1.o/2.o/3.o). */
    private function competition(string $name, ?array $points, string $format = 'head_to_head', bool $withRow = true): Season
    {
        $sport = Sport::factory()->create(['format_type' => $format]);
        $event = $withRow ? GamesEditionEvent::factory()->create([
            'games_edition_id' => $this->edition->id, 'label' => $name, 'sport_id' => $sport->id,
            'pts_first' => $points[0] ?? null, 'pts_second' => $points[1] ?? null, 'pts_third' => $points[2] ?? null,
        ]) : null;

        return Season::factory()->create([
            'games_edition_id' => $this->edition->id, 'games_edition_event_id' => $event?->id,
            'sport_id' => $sport->id, 'name' => $name,
        ]);
    }

    private function podiumStage(Season $season): SeasonStage
    {
        return SeasonStage::factory()->create(['season_id' => $season->id, 'name' => 'Final', 'mode' => StageMode::DirectPodium]);
    }

    /** Registra un podio directo: $teams por puesto (un Team o una lista). */
    private function podium(Season $season, array $places): SeasonStage
    {
        $stage = $season->stages()->first() ?? $this->podiumStage($season);

        app(DirectPodiumService::class)->save($stage, collect($places)->map(
            fn ($teams) => collect(is_array($teams) ? $teams : [$teams])->map(fn (Team $t) => ['team_id' => $t->id])->all()
        )->all(), $this->organizer);

        return $stage;
    }

    /** Inscripciones (season_team) de las delegaciones dadas, en ese orden. */
    private function seasonTeams(Season $season, array $indexes): Collection
    {
        return collect($indexes)->map(fn (int $i) => SeasonTeam::firstOrCreate(
            ['season_id' => $season->id, 'team_id' => $this->teams[$i]->id], ['is_active' => true],
        ));
    }

    /**
     * Llave de 4 con tercer puesto: semifinales 1.o-4.o y 2.o-3.o de la lista.
     *
     * @return array{semis: Collection, final: GameMatch, third: GameMatch}
     */
    private function bracketOfFour(Season $season, array $indexes = [0, 1, 2, 3]): array
    {
        $stage = $this->stage(['name' => 'Fase final', 'has_third_place' => true, 'sort_order' => 9], $season);
        $this->bracket($stage, $this->seasonTeams($season, $indexes));
        $of = fn (RoundType $type) => GameMatch::where('season_stage_id', $stage->id)->where('round_type', $type->value)->orderBy('bracket_slot')->get();

        return ['semis' => $of(RoundType::SemiFinal), 'final' => $of(RoundType::Final)->sole(), 'third' => $of(RoundType::ThirdPlace)->sole()];
    }

    /** @return array<string, array{0: ?int, 1: int, 2: int, 3: int, 4: int}> nombre => [pos, oro, plata, bronce, puntos] */
    private function table(): array
    {
        return app(AwardService::class)->table($this->edition->fresh())
            ->mapWithKeys(fn ($row) => [$row->team->name => [$row->position, $row->gold, $row->silver, $row->bronze, $row->total_points]])
            ->all();
    }

    private function points(Team $team): int
    {
        return (int) GamesEditionMedal::where('games_edition_id', $this->edition->id)->where('team_id', $team->id)->value('total_points');
    }

    // ── Calculo ─────────────────────────────────────────────────────────

    public function test_direct_podium_awards_row_points(): void
    {
        $cycling = $this->competition('Ciclismo Libre Varones', [20, 16, 12], 'individual_ranked');

        $this->podium($cycling, [1 => $this->teams[0], 2 => $this->teams[1], 3 => $this->teams[2]]);

        $this->assertSame([20, 16, 12], [$this->points($this->teams[0]), $this->points($this->teams[1]), $this->points($this->teams[2])]);
        $this->assertSame([1, 2, 3], EditionAward::orderBy('place')->pluck('place')->all());
        $this->assertSame([$cycling->games_edition_event_id], EditionAward::distinct()->pluck('games_edition_event_id')->all());
    }

    public function test_bracket_final_and_third_place(): void
    {
        $football = $this->competition('Fútbol Libre Varones', [24, 20, 16]);
        $b = $this->bracketOfFour($football);

        $this->close($b['semis'][0], 2, 0); // gana 1, pierde 4
        $this->close($b['semis'][1], 0, 1); // gana 3, pierde 2
        $this->close($b['third'], 0, 3);    // tercer puesto: 2 le gana a 4
        $this->close($b['final'], 1, 0);    // campeon 1, subcampeon 3

        $this->assertSame(24, $this->points($this->teams[0]));
        $this->assertSame(20, $this->points($this->teams[2]));
        $this->assertSame(16, $this->points($this->teams[1]));
        $this->assertSame(0, $this->points($this->teams[3]));
        $this->assertSame(3, EditionAward::count());
    }

    public function test_no_bronze_without_third_place_match(): void
    {
        $football = $this->competition('Fútbol Libre Varones', [24, 20, 16]);
        $b = $this->bracketOfFour($football);
        $this->close($b['semis'][0], 2, 0);
        $this->close($b['semis'][1], 0, 1);

        // Con las semifinales jugadas aun no hay ningun premio.
        $this->assertSame(0, EditionAward::count());

        $this->close($b['final'], 1, 0);

        // Final jugada y tercer puesto pendiente: solo 1.o y 2.o (Art. 10).
        $this->assertSame([1, 2], EditionAward::orderBy('place')->pluck('place')->all());
    }

    public function test_bracket_of_three_gives_third_to_the_semifinal_loser(): void
    {
        $basket = $this->competition('Básquetbol Máster Damas', [20, 16, 12]);
        $stage = $this->stage(['name' => 'Fase final', 'has_third_place' => true], $basket);
        $this->bracket($stage, $this->seasonTeams($basket, [0, 1, 2]));

        $this->close(GameMatch::where('round_type', 'semifinal')->sole(), 3, 1); // 2 le gana a 3
        $this->close(GameMatch::where('round_type', 'final')->sole(), 0, 2);     // 2 le gana a 1

        $this->assertSame([16, 20, 12], [$this->points($this->teams[0]), $this->points($this->teams[1]), $this->points($this->teams[2])]);
    }

    public function test_series_stage_does_not_award(): void
    {
        $football = $this->competition('Fútbol Libre Varones', [24, 20, 16]);
        $series = $this->stage(['name' => 'Serie A', 'sort_order' => 1, 'qualifiers' => 1], $football);
        $this->stage(['name' => 'Fase final', 'sort_order' => 2, 'has_third_place' => true], $football);
        $this->bracket($series, $this->seasonTeams($football, [0, 1]));

        $this->close(GameMatch::where('season_stage_id', $series->id)->sole(), 2, 1);

        $this->assertSame(0, EditionAward::count());
        $this->assertSame(0, GamesEditionMedal::count());
    }

    public function test_row_without_points_counts_medal_but_zero_points(): void
    {
        $dance = $this->competition('Danza', null, 'individual_ranked');

        $this->podium($dance, [1 => $this->teams[0], 2 => $this->teams[1]]);

        $this->assertSame([1, 1, 0, 0, 0], $this->table()['Delegación 1']);
        $this->assertSame([2, 0, 1, 0, 0], $this->table()['Delegación 2']);
        $this->assertSame([0, 0], EditionAward::pluck('points')->all());
    }

    public function test_competition_without_row_awards_zero_points(): void
    {
        $manual = $this->competition('Exhibición', null, 'individual_ranked', withRow: false);

        $this->podium($manual, [1 => $this->teams[0]]);

        $award = EditionAward::sole();
        $this->assertSame([0, null, 1], [$award->points, $award->games_edition_event_id, $award->place]);
        $this->assertSame([1, 1, 0, 0, 0], $this->table()['Delegación 1']);
    }

    public function test_shared_place_gives_full_points_to_each(): void
    {
        $sapo = $this->competition('Sapo Libre Damas', [10, 8, 6], 'individual_ranked');

        $this->podium($sapo, [1 => $this->teams[0], 2 => $this->teams[1], 3 => [$this->teams[2], $this->teams[3]]]);

        $this->assertSame(6, $this->points($this->teams[2]));
        $this->assertSame(6, $this->points($this->teams[3]));
        $this->assertSame(30, (int) EditionAward::sum('points'));
    }

    public function test_individual_award_goes_to_the_player_delegation(): void
    {
        $chess = $this->competition('Ajedrez Libre Damas', [8, 6, 4], 'individual_ranked');
        $stage = $this->podiumStage($chess);
        $entry = $this->rosterEntry($this->seasonTeams($chess, [1])->first());

        app(DirectPodiumService::class)->save($stage, [1 => ['team_id' => $this->teams[1]->id, 'season_team_player_id' => $entry->id]], $this->organizer);

        $this->assertSame(8, $this->points($this->teams[1]));

        // Y en una prueba con marcas, el premio va a la delegacion del plantel del participante.
        $race = $this->competition('100 metros', [6, 4, 2], 'individual_ranked');
        $runner = $this->rosterEntry($this->seasonTeams($race, [3])->first());
        $match = $this->looseMatch($race, 'final');
        $p = EventParticipant::create(['match_id' => $match->id, 'participant_type' => 'season_team_player', 'participant_id' => $runner->id]);
        app(ResultService::class)->close($match, ['results' => [$p->id => 12.5]], $this->organizer);

        $this->assertSame(6, $this->points($this->teams[3]));
    }

    private function looseMatch(Season $season, string $round = 'regular'): GameMatch
    {
        $matchday = Matchday::firstOrCreate(['season_id' => $season->id, 'name' => 'Jornada única']);

        return GameMatch::create(['season_id' => $season->id, 'matchday_id' => $matchday->id, 'round_type' => $round, 'status' => 'pending']);
    }

    public function test_ranked_competition_without_stages_uses_positions(): void
    {
        $race = $this->competition('Atletismo — 100 metros Libre Varones', [6, 4, 2], 'individual_ranked');
        $match = $this->looseMatch($race, 'final');
        $marks = [];

        // Sin disciplina, mayor marca = mejor puesto. La 4.a no sube al podio.
        foreach ([0 => 40, 1 => 30, 2 => 20, 3 => 10] as $teamIndex => $mark) {
            $entry = $this->rosterEntry($this->seasonTeams($race, [$teamIndex])->first());
            $p = EventParticipant::create(['match_id' => $match->id, 'participant_type' => 'season_team_player', 'participant_id' => $entry->id]);
            $marks[$p->id] = $mark;
        }

        app(ResultService::class)->close($match, ['results' => $marks], $this->organizer);

        $this->assertSame([6, 4, 2, 0], $this->teams->take(4)->map(fn ($t) => $this->points($t))->all());

        // Una serie eliminatoria (ronda "heat") no premia.
        $heat = $this->looseMatch($race, 'heat');
        $entry = $this->rosterEntry($this->seasonTeams($race, [4])->first());
        $p = EventParticipant::create(['match_id' => $heat->id, 'participant_type' => 'season_team_player', 'participant_id' => $entry->id]);
        app(ResultService::class)->close($heat, ['results' => [$p->id => 99]], $this->organizer);

        $this->assertSame(0, $this->points($this->teams[4]));
    }

    public function test_head_to_head_without_stages_awards_only_the_final(): void
    {
        $tennis = $this->competition('Tenis de mesa Libre Varones', [6, 4, 2]);
        [$a, $b] = $this->seasonTeams($tennis, [0, 1])->all();

        foreach (['regular', 'final'] as $round) {
            $match = $this->looseMatch($tennis, $round);
            Matchday::where('id', $match->matchday_id)->update(['name' => "Jornada {$round}"]);
            EventParticipant::create(['match_id' => $match->id, 'participant_type' => 'season_team', 'participant_id' => $a->id, 'side' => 'home']);
            EventParticipant::create(['match_id' => $match->id, 'participant_type' => 'season_team', 'participant_id' => $b->id, 'side' => 'away']);
            $this->close($match, 0, 2);
        }

        $this->assertSame([4, 6], [$this->points($this->teams[0]), $this->points($this->teams[1])]);
        $this->assertSame(2, EditionAward::count());
    }

    public function test_totals_accumulate_across_competitions(): void
    {
        $football = $this->competition('Fútbol Libre Varones', [24, 20, 16], 'individual_ranked');
        $volley = $this->competition('Voleibol Libre Damas', [20, 16, 12], 'individual_ranked');

        $this->podium($football, [1 => $this->teams[0], 2 => $this->teams[1]]);
        $this->podium($volley, [1 => $this->teams[2], 2 => $this->teams[0]]);

        // Oro en futbol (24) + plata en voley Libre (16) = 40.
        $this->assertSame([1, 1, 1, 0, 40], $this->table()['Delegación 1']);
        $this->assertSame(40, $this->points($this->teams[0]));
    }

    public function test_table_order_and_ties(): void
    {
        $a = $this->competition('A', [10, 6, 4], 'individual_ranked');
        $b = $this->competition('B', [10, 6, 4], 'individual_ranked');
        $c = $this->competition('C', [6, 4, 0], 'individual_ranked');
        $d = $this->competition('D', [12, 5, 5], 'individual_ranked');

        // D1: oro A (10). D2: oro B (10). D3: plata A + plata C (6+4 = 10), sin oros.
        // D4: oro C (6) + bronce A (4) = 10 con 1 oro y 1 bronce. D5: 12 por el oro de D.
        $this->podium($a, [1 => $this->teams[0], 2 => $this->teams[2], 3 => $this->teams[3]]);
        $this->podium($b, [1 => $this->teams[1]]);
        $this->podium($c, [1 => $this->teams[3], 2 => $this->teams[2]]);
        $this->podium($d, [1 => $this->teams[4]]);

        $table = $this->table();

        $this->assertSame(['Delegación 5', 'Delegación 4', 'Delegación 1', 'Delegación 2', 'Delegación 3'], array_keys($table));
        $this->assertSame([1, 2, 3, 3, 5], array_column($table, 0));
        $this->assertSame([12, 10, 10, 10, 10], array_column($table, 4));
    }

    public function test_table_lists_delegations_with_zero(): void
    {
        $this->podium($this->competition('A', [10, 6, 4], 'individual_ranked'), [1 => $this->teams[4]]);

        $table = $this->table();

        $this->assertCount(5, $table);
        $this->assertSame([1, 1, 0, 0, 10], $table['Delegación 5']);
        // Las que no suman comparten la posicion siguiente, en orden alfabetico.
        $this->assertSame([[2, 0, 0, 0, 0]], array_values(array_unique(array_slice(array_values($table), 1), SORT_REGULAR)));
        $this->assertSame(['Delegación 5', 'Delegación 1', 'Delegación 2', 'Delegación 3', 'Delegación 4'], array_keys($table));
    }

    public function test_disqualified_delegation_goes_last_without_position(): void
    {
        $this->podium($this->competition('A', [10, 6, 4], 'individual_ranked'), [1 => $this->teams[0], 2 => $this->teams[1]]);
        $entry = $this->edition->editionTeams()->where('team_id', $this->teams[0]->id)->sole();

        app(EditionTeamService::class)->update($entry, [
            'group_id' => null, 'delegate_id' => null, 'substitute_delegate_id' => null, 'status' => 'disqualified',
        ], $this->organizer);

        $rows = app(AwardService::class)->table($this->edition);
        $last = $rows->last();

        $this->assertSame('Delegación 1', $last->team->name);
        $this->assertTrue($last->disqualified);
        $this->assertNull($last->position);
        // Conserva sus premios en el detalle.
        $this->assertSame(10, $last->total_points);
        $this->assertCount(1, app(AwardService::class)->detail($this->edition, $this->teams[0]));
        // El resto sube: la subcampeona queda primera.
        $this->assertSame(['Delegación 2', 1], [$rows->first()->team->name, $rows->first()->position]);
    }

    public function test_reopening_a_final_recalculates_without_duplicates(): void
    {
        $football = $this->competition('Fútbol Libre Varones', [24, 20, 16]);
        $other = $this->competition('Danza', [5, 3, 1], 'individual_ranked');
        $this->podium($other, [1 => $this->teams[4]]);

        $b = $this->bracketOfFour($football);
        $this->close($b['semis'][0], 2, 0);
        $this->close($b['semis'][1], 0, 1);
        $this->close($b['final'], 1, 0); // campeon 1 (24), subcampeon 3 (20)

        $this->assertSame(49, (int) GamesEditionMedal::sum('total_points'));

        $this->reopen($b['final']);

        // Los premios de esa competencia desaparecen; los de la otra siguen.
        $this->assertSame(0, EditionAward::where('season_id', $football->id)->count());
        $this->assertSame(5, (int) GamesEditionMedal::sum('total_points'));

        $this->close($b['final'], 0, 2); // ahora gana 3

        $this->assertSame([20, 24], [$this->points($this->teams[0]), $this->points($this->teams[2])]);
        $this->assertSame(49, (int) GamesEditionMedal::sum('total_points'));
        $this->assertSame(2, EditionAward::where('season_id', $football->id)->count());
    }

    public function test_changing_row_points_recalculates(): void
    {
        $cycling = $this->competition('Ciclismo Libre Varones', [20, 16, 12], 'individual_ranked');
        $this->podium($cycling, [1 => $this->teams[0], 2 => $this->teams[1], 3 => $this->teams[2]]);

        app(EditionEventService::class)->update($cycling->event, [
            'label' => 'Ciclismo Libre Varones', 'gender_scope' => 'male', 'sport_id' => $cycling->sport_id,
            'pts_first' => 30, 'pts_second' => 20, 'pts_third' => 10,
        ], $this->admin());

        $this->assertSame([30, 20, 10], [$this->points($this->teams[0]), $this->points($this->teams[1]), $this->points($this->teams[2])]);
    }

    public function test_rebuild_is_idempotent(): void
    {
        $this->podium($this->competition('A', [10, 6, 4], 'individual_ranked'), [1 => $this->teams[0], 2 => $this->teams[1], 3 => [$this->teams[2], $this->teams[3]]]);
        $snapshot = fn () => [
            EditionAward::orderBy('team_id')->orderBy('place')->get(['season_id', 'team_id', 'place', 'points'])->toArray(),
            GamesEditionMedal::orderBy('team_id')->get(['team_id', 'gold', 'silver', 'bronze', 'total_points'])->toArray(),
        ];
        $before = $snapshot();

        app(AwardService::class)->rebuild($this->edition);
        app(AwardService::class)->rebuild($this->edition);

        $this->assertSame($before, $snapshot());
        $this->assertSame(4, EditionAward::count());
    }

    public function test_inactive_competition_is_ignored(): void
    {
        $a = $this->competition('A', [10, 6, 4], 'individual_ranked');
        $this->podium($a, [1 => $this->teams[0]]);
        $this->assertSame(10, $this->points($this->teams[0]));

        // Desactivarla desde el Service ya recalcula.
        app(SeasonService::class)->update($a, [
            'games_edition_id' => $this->edition->id, 'sport_id' => $a->sport_id, 'name' => 'A', 'start_date' => null, 'end_date' => null,
            'status' => 'active', 'is_active' => false, 'games_edition_event_id' => $a->games_edition_event_id, 'category_id' => null, 'gender' => null,
        ], [['id' => null, 'phase_name' => 'Fase única', 'pts_win' => 3, 'pts_draw' => 1, 'pts_loss' => 0, 'pts_walkover' => -1]], $this->organizer);

        $this->assertSame(0, EditionAward::count());
        $this->assertSame(0, $this->points($this->teams[0]));
    }

    public function test_only_the_last_stage_counts_when_stages_change(): void
    {
        $a = $this->competition('A', [10, 6, 4], 'individual_ranked');
        $first = $this->podium($a, [1 => $this->teams[0]]);

        // Una fase posterior pasa a ser la que premia.
        $later = SeasonStage::factory()->create(['season_id' => $a->id, 'name' => 'Gran final', 'mode' => StageMode::DirectPodium, 'sort_order' => 5]);
        app(DirectPodiumService::class)->save($later, [1 => ['team_id' => $this->teams[1]->id]], $this->organizer);

        $this->assertSame([0, 10], [$this->points($this->teams[0]), $this->points($this->teams[1])]);
        $this->assertSame(1, $first->results()->count());
    }

    // ── Pantallas ───────────────────────────────────────────────────────

    public function test_delegate_sees_the_general_score_of_his_edition_only(): void
    {
        $this->podium($this->competition('A', [10, 6, 4], 'individual_ranked'), [1 => $this->teams[0]]);
        $delegate = $this->delegate();
        $this->registerDelegation($this->edition, $this->teams[1], $delegate);

        $foreign = GamesEdition::factory()->create(['name' => 'Olimpiada Ajena']);
        $foreignTeam = Team::factory()->create(['name' => 'Delegación Ajena']);
        $this->registerDelegation($foreign, $foreignTeam);

        $this->actingAs($delegate)->get(route('reports.general-score'))
            ->assertOk()->assertSee($this->edition->name)->assertSee('Delegación 1')->assertDontSee('Olimpiada Ajena');

        // Forzar la olimpiada ajena por la URL no la muestra.
        $this->actingAs($delegate)->get(route('reports.general-score', ['olimpiada' => $foreign->id]))
            ->assertOk()->assertDontSee('Delegación Ajena');

        Livewire::actingAs($delegate)->test(GeneralScore::class)
            ->set('editionId', (string) $foreign->id)
            ->assertDontSee('Delegación Ajena')
            ->set('editionId', (string) $this->edition->id)
            ->call('toggleTeam', $this->teams[0]->id)
            ->assertSee('10 pts');
    }

    public function test_public_page_shows_general_score(): void
    {
        $a = $this->competition('Ciclismo Libre Varones', [20, 16, 12], 'individual_ranked');
        $this->podium($a, [1 => $this->teams[3], 2 => $this->teams[0]]);

        $component = Livewire::test(PublicResults::class)
            ->call('setTab', 'general')
            ->assertSet('activeTab', 'general')
            ->assertSee('Puntaje general');

        // La lider y su total, sin datos personales: solo delegaciones y puntos.
        $rows = $component->viewData('generalScore');
        $this->assertSame(['Delegación 4', 20], [$rows[0]->team->name, $rows[0]->total_points]);
        $this->assertSame(['Delegación 1', 16], [$rows[1]->team->name, $rows[1]->total_points]);
        $this->assertStringContainsString('>20<', $component->html());

        $this->get(route('public.results'))->assertOk();
    }

    public function test_only_manager_can_recalculate(): void
    {
        $delegate = $this->delegate();
        $this->registerDelegation($this->edition, $this->teams[0], $delegate);
        $a = $this->competition('A', [10, 6, 4], 'individual_ranked');
        $this->podium($a, [1 => $this->teams[0]]);

        Livewire::actingAs($delegate)->test(GeneralScore::class)->call('recalculate')->assertForbidden();
        $this->actingAs($delegate)->get(route('reports.general-score'))->assertOk()->assertDontSee('Recalcular');

        // Si el dato derivado se dana, quien gestiona la olimpiada lo reconstruye.
        GamesEditionMedal::query()->delete();
        Livewire::actingAs($this->organizer)->test(GeneralScore::class)->assertSee('Recalcular')->call('recalculate')->assertDispatched('toast');
        $this->assertSame(10, $this->points($this->teams[0]));

        $this->actingAs(\App\Models\User::factory()->create())->get(route('reports.general-score'))->assertForbidden();
    }

    public function test_puno_table_matches_page_9_sums(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->seed(\Database\Seeders\Puno2026Seeder::class);
        $puno = GamesEdition::where('name', \Database\Seeders\Puno2026Seeder::EDITION_NAME)->firstOrFail();
        $admin = $this->admin();
        app(\App\Services\CompetitionGeneratorService::class)->generate($puno, $admin);

        $teams = $puno->editionTeams()->with('team')->get()->pluck('team')->keyBy('name');
        $podium = function (string $competition, array $names) use ($puno, $admin, $teams) {
            $season = $puno->seasons()->where('name', $competition)->firstOrFail();
            $stage = SeasonStage::factory()->create(['season_id' => $season->id, 'name' => 'Final', 'mode' => StageMode::DirectPodium]);
            app(DirectPodiumService::class)->save($stage, collect($names)->mapWithKeys(fn ($n, $i) => [$i + 1 => ['team_id' => $teams[$n]->id]])->all(), $admin);
        };

        // Tres podios, sumados a mano con el cuadro de la pag. 9:
        //   Puntualidad y presentacion 18/14/10 · Ciclismo Libre varones 20/16/12 · Futsal Super Master varones 28/24/20
        $podium('Puntualidad y presentación', ['JAE Puno', 'Ilave', 'IESP Juli']);
        $podium('Ciclismo Libre Varones', ['Ilave', 'JAE Puno', 'Huancané']);
        $podium('Futsal Súper Máster Varones', ['JAE Puno', 'IESP Juli', 'Ilave']);

        $table = app(AwardService::class)->table($puno)->keyBy(fn ($r) => $r->team->name);

        $this->assertCount(18, $table);
        $this->assertSame([1, 62], [$table['JAE Puno']->position, $table['JAE Puno']->total_points]);     // 18 + 16 + 28
        $this->assertSame([2, 54], [$table['Ilave']->position, $table['Ilave']->total_points]);           // 14 + 20 + 20
        $this->assertSame([3, 34], [$table['IESP Juli']->position, $table['IESP Juli']->total_points]);   // 10 + 24
        $this->assertSame([4, 12], [$table['Huancané']->position, $table['Huancané']->total_points]);
        $this->assertSame([5, 0], [$table['Ayaviri']->position, $table['Ayaviri']->total_points]);
    }
}
