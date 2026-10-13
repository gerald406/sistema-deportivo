<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Enums\MatchStatus;
use App\Enums\RoundType;
use App\Models\GameMatch;
use App\Models\GamesEditionMedal;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Services\BracketService;
use App\Services\StageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsBracket;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** F5 (Art. 8 y 10; C12): llaves de eliminacion simple con byes, avance y tercer puesto. */
class BracketTest extends TestCase
{
    use BuildsBracket, BuildsCompetition, CreatesActors, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootCompetition();
    }

    private function ofType(RoundType $type)
    {
        return GameMatch::where('round_type', $type->value)->orderBy('bracket_slot')->get();
    }

    public function test_four_participants(): void
    {
        $teams = $this->entrants(4);

        $matches = $this->bracket($this->stage(['has_third_place' => true]), $teams);

        $this->assertCount(4, $matches);
        $semis = $this->ofType(RoundType::SemiFinal);
        $final = $this->ofType(RoundType::Final)->sole();
        $third = $this->ofType(RoundType::ThirdPlace)->sole();

        // 1.o vs 4.o y 2.o vs 3.o.
        $this->assertSame(['home' => $teams[0]->id, 'away' => $teams[3]->id], $this->seats($semis[0]));
        $this->assertSame(['home' => $teams[1]->id, 'away' => $teams[2]->id], $this->seats($semis[1]));

        foreach ($semis as $i => $semi) {
            $this->assertSame($final->id, $semi->next_match_id);
            $this->assertSame($third->id, $semi->loser_next_match_id);
            $this->assertSame($i === 0 ? 'home' : 'away', $semi->next_match_side);
            $this->assertSame(MatchStatus::Pending, $semi->status);
        }

        $this->assertSame(['home' => null, 'away' => null], $this->seats($final));
        $this->assertSame(['home' => null, 'away' => null], $this->seats($third));

        // Cada ronda en su jornada.
        $this->assertSame(3, GameMatch::distinct()->count('matchday_id'));

        // Sin tercer puesto: 3 partidos.
        $other = $this->headToHeadSeason();
        $this->assertCount(3, $this->bracket($this->stage(season: $other), $this->entrants(4, $other)));
    }

    public function test_five_participants_get_three_byes(): void
    {
        $teams = $this->entrants(5);

        $this->bracket($this->stage(), $teams);

        // Tamano 8: 1 partido de primera ronda (4.o vs 5.o), 2 semifinales y final.
        $first = GameMatch::where('bracket_round', 1)->get();
        $this->assertCount(1, $first);
        $this->assertSame(['home' => $teams[3]->id, 'away' => $teams[4]->id], $this->seats($first[0]));
        $this->assertSame(4, GameMatch::count());

        // Los 3 primeros descansan y ya figuran en la ronda siguiente.
        $semis = $this->ofType(RoundType::SemiFinal);
        $this->assertSame(['home' => $teams[0]->id, 'away' => null], $this->seats($semis[0]));
        $this->assertSame(['home' => $teams[1]->id, 'away' => $teams[2]->id], $this->seats($semis[1]));
        $this->assertSame($semis[0]->id, $first[0]->next_match_id);
        $this->assertSame('away', $first[0]->next_match_side);
    }

    public function test_three_participants_get_one_bye(): void
    {
        $teams = $this->entrants(3);

        $matches = $this->bracket($this->stage(['has_third_place' => true]), $teams);

        // Tamano 4: un partido (2.o vs 3.o) y la final con el 1.o esperando.
        // Sin dos semifinales reales no hay partido por el tercer puesto.
        $this->assertCount(2, $matches);
        $semi = $this->ofType(RoundType::SemiFinal)->sole();
        $final = $this->ofType(RoundType::Final)->sole();

        $this->assertSame(['home' => $teams[1]->id, 'away' => $teams[2]->id], $this->seats($semi));
        $this->assertSame(['home' => $teams[0]->id, 'away' => null], $this->seats($final));
        $this->assertNull($semi->loser_next_match_id);
    }

    public function test_two_participants(): void
    {
        $teams = $this->entrants(2);

        $matches = $this->bracket($this->stage(['has_third_place' => true]), $teams);

        $this->assertCount(1, $matches);
        $this->assertSame(RoundType::Final, $matches[0]->round_type);
        $this->assertSame(['home' => $teams[0]->id, 'away' => $teams[1]->id], $this->seats($matches[0]));
        $this->assertNull($matches[0]->next_match_id);
    }

    public function test_no_match_pairs_two_byes(): void
    {
        foreach (range(2, 16) as $count) {
            $season = $this->headToHeadSeason();
            $teams = $this->entrants($count, $season);
            $this->bracket($this->stage(season: $season), $teams);

            $matches = GameMatch::where('season_id', $season->id)->withCount('participants')->get();
            $size = 2 ** (int) ceil(log($count, 2));

            // Un partido menos que participantes (eliminacion simple).
            $this->assertCount($count - 1, $matches, "N = {$count}");

            // Ningun partido de primera ronda queda incompleto y nadie se pierde ni se repite.
            foreach ($matches->where('bracket_round', 1) as $match) {
                $this->assertSame(2, $match->participants_count, "N = {$count}");
            }
            $this->assertCount($count - ($size - $count), $matches->where('bracket_round', 1)->flatMap(fn ($m) => $m->participants), "N = {$count}");

            $seated = $matches->flatMap(fn ($m) => $m->participants->pluck('participant_id'));
            $this->assertEqualsCanonicalizing($teams->pluck('id')->all(), $seated->all(), "N = {$count}");

            // Con byes, ningun partido de segunda ronda nace vacio de ambos lados sin tener de donde llenarse.
            foreach ($matches->where('bracket_round', 2) as $match) {
                $feeders = $matches->where('next_match_id', $match->id)->count();
                $this->assertSame(2, $feeders + $match->participants_count, "N = {$count}");
            }
        }
    }

    public function test_seed_order_keeps_top_seeds_apart(): void
    {
        $service = app(BracketService::class);

        $this->assertSame([1, 2], $service->seedOrder(2));
        $this->assertSame([1, 4, 2, 3], $service->seedOrder(4));
        $this->assertSame([1, 8, 4, 5, 2, 7, 3, 6], $service->seedOrder(8));
    }

    public function test_winner_advances_and_loser_goes_to_third_place(): void
    {
        $teams = $this->entrants(4);
        $this->bracket($this->stage(['has_third_place' => true]), $teams);
        [$semiA, $semiB] = $this->ofType(RoundType::SemiFinal)->all();
        $final = $this->ofType(RoundType::Final)->sole();
        $third = $this->ofType(RoundType::ThirdPlace)->sole();

        $this->close($semiA, 2, 0);

        $this->assertSame(['home' => $teams[0]->id, 'away' => null], $this->seats($final));
        $this->assertSame(['home' => $teams[3]->id, 'away' => null], $this->seats($third));

        // Por walkover tambien se avanza: no se presenta el local (2.o).
        app(\App\Services\ResultService::class)->close($semiB->fresh(), ['walkover' => 'home'], $this->organizer);

        $this->assertSame(['home' => $teams[0]->id, 'away' => $teams[2]->id], $this->seats($final));
        $this->assertSame(['home' => $teams[3]->id, 'away' => $teams[1]->id], $this->seats($third));
    }

    public function test_reopening_removes_advanced_participants(): void
    {
        $teams = $this->entrants(4);
        $this->bracket($this->stage(['has_third_place' => true]), $teams);
        $semiA = $this->ofType(RoundType::SemiFinal)[0];
        $final = $this->ofType(RoundType::Final)->sole();
        $third = $this->ofType(RoundType::ThirdPlace)->sole();
        $this->close($semiA, 2, 0);

        $this->reopen($semiA);

        $this->assertSame(['home' => null, 'away' => null], $this->seats($final));
        $this->assertSame(['home' => null, 'away' => null], $this->seats($third));

        // Al corregir el resultado avanza el otro.
        $this->close($semiA, 0, 1);
        $this->assertSame($teams[3]->id, $this->seats($final)['home']);
        $this->assertSame($teams[0]->id, $this->seats($third)['home']);
    }

    public function test_reopening_is_blocked_when_next_round_was_played(): void
    {
        $teams = $this->entrants(4);
        $this->bracket($this->stage(), $teams);
        [$semiA, $semiB] = $this->ofType(RoundType::SemiFinal)->all();
        $final = $this->ofType(RoundType::Final)->sole();
        $this->close($semiA, 2, 0);
        $this->close($semiB, 3, 1);
        $this->close($final, 1, 0);

        try {
            $this->reopen($semiA);
            $this->fail('Se reabrió una semifinal con la final ya jugada.');
        } catch (ValidationException $e) {
            $this->assertSame(BracketService::REOPEN_BLOCKED, $e->errors()['reason'][0]);
        }

        $this->assertSame(MatchStatus::Played, $semiA->fresh()->status);
        $this->assertSame($teams[0]->id, $this->seats($final)['home']);

        // Reabriendo primero la final, si se puede.
        $this->reopen($final);
        $this->reopen($semiA);
        $this->assertSame(['home' => null, 'away' => $teams[1]->id], $this->seats($final));
    }

    public function test_stage_with_matches_cannot_be_regenerated(): void
    {
        $teams = $this->entrants(4);
        $stage = $this->stage();
        $this->bracket($stage, $teams);

        try {
            $this->bracket($stage, $teams);
            $this->fail('Se generó dos veces la llave de una fase.');
        } catch (ValidationException) {
            $this->assertSame(3, GameMatch::count());
        }

        // Una fase con partidos tampoco se borra.
        $this->assertIsString(app(StageService::class)->delete($stage, $this->organizer));

        // Borrar la llave (sin resultados) permite generarla de nuevo.
        $this->assertTrue(app(BracketService::class)->clear($stage, $this->organizer));
        $this->assertSame(0, GameMatch::count());
        $this->assertCount(3, $this->bracket($stage, $teams->reverse()->values()));
    }

    public function test_bracket_with_results_cannot_be_cleared(): void
    {
        $stage = $this->stage();
        $this->bracket($stage, $this->entrants(4));
        $this->close($this->ofType(RoundType::SemiFinal)[0], 1, 0);

        $this->assertIsString(app(BracketService::class)->clear($stage, $this->organizer));
        $this->assertSame(3, GameMatch::count());
    }

    public function test_participants_must_belong_to_the_competition(): void
    {
        $teams = $this->entrants(3);
        $foreign = SeasonTeam::create(['season_id' => Season::factory()->create()->id, 'team_id' => Team::factory()->create()->id, 'is_active' => true]);
        $stage = $this->stage();
        $service = app(BracketService::class);

        $invalid = [
            [...$teams->pluck('id')->all(), $foreign->id],        // de otra competencia
            [$teams[0]->id],                                      // menos de 2
            [$teams[0]->id, $teams[1]->id, $teams[0]->id],        // repetido
        ];

        foreach ($invalid as $ids) {
            try {
                $service->generate($stage, $ids, $this->organizer);
                $this->fail('Se aceptó una lista de participantes inválida.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        // Tampoco en una fase de podio directo.
        try {
            $service->generate($this->stage(['mode' => 'direct_podium']), $teams->pluck('id')->all(), $this->organizer);
            $this->fail('Se generó una llave en una fase de podio directo.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, GameMatch::count());
    }

    public function test_series_winner_is_reported(): void
    {
        $teams = $this->entrants(3);
        $stage = $this->stage(['name' => 'Serie A', 'qualifiers' => 1]);
        $this->bracket($stage, $teams);
        $service = app(StageService::class);

        $this->assertCount(0, $service->winners($stage));

        $this->close($this->ofType(RoundType::SemiFinal)->sole(), 0, 2); // gana el 3.o
        $this->assertCount(0, $service->winners($stage));

        $this->close($this->ofType(RoundType::Final)->sole(), 1, 3);     // 1.o pierde con el 3.o

        $this->assertSame([$teams[2]->id], $service->winners($stage)->pluck('id')->all());

        // Si la serie clasifica a dos, tambien pasa el finalista.
        $stage->update(['qualifiers' => 2]);
        $this->assertSame([$teams[2]->id, $teams[0]->id], $service->winners($stage)->pluck('id')->all());
    }

    public function test_only_edition_manager_generates(): void
    {
        $teams = $this->entrants(4);
        $stage = $this->stage();

        foreach ([$this->organizer(), $this->delegate()] as $stranger) {
            try {
                app(BracketService::class)->generate($stage, $teams->pluck('id')->all(), $stranger);
                $this->fail('Un usuario ajeno generó la llave.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }

            try {
                app(BracketService::class)->clear($stage, $stranger);
                $this->fail('Un usuario ajeno borró la llave.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, GameMatch::count());
        $this->assertCount(3, app(BracketService::class)->generate($stage, $teams->pluck('id')->all(), $this->admin()));
    }

    public function test_match_screens_render_with_incomplete_bracket_matches(): void
    {
        // La final y el tercer puesto aun no tienen participantes.
        $this->bracket($this->stage(['has_third_place' => true]), $this->entrants(4));

        foreach (['results.index', 'matches.index', 'periods.index', 'match-events.index', 'matchdays.index', 'suspensions.index', 'relays.index', 'reports.index'] as $name) {
            foreach ([$this->organizer, $this->admin()] as $actor) {
                $status = $this->actingAs($actor)->get(route($name))->getStatusCode();
                $this->assertSame(200, $status, "La ruta {$name} devolvió {$status} con una llave a medio jugar.");
            }
        }

        $this->get(route('home'))->assertOk();
    }

    public function test_medals_come_only_from_the_final_stage(): void
    {
        // Serie A (clasifica 1) y Fase final con tercer puesto.
        $series = $this->stage(['name' => 'Serie A', 'sort_order' => 1, 'qualifiers' => 1]);
        $final = $this->stage(['name' => 'Fase final', 'sort_order' => 2, 'has_third_place' => true]);
        $teams = $this->entrants(6);

        $this->bracket($series, $teams->take(2));
        $this->close(GameMatch::where('season_stage_id', $series->id)->sole(), 1, 0);

        // La final de una serie no da medallas.
        $this->assertSame(0, GamesEditionMedal::count());

        $this->bracket($final, $teams->slice(2)->values());
        $stageMatches = fn (RoundType $type) => GameMatch::where('season_stage_id', $final->id)->where('round_type', $type->value)->orderBy('bracket_slot')->get();
        [$semiA, $semiB] = $stageMatches(RoundType::SemiFinal)->all();
        $this->close($semiA, 1, 0); // gana teams[2], pierde teams[5]
        $this->close($semiB, 1, 0); // gana teams[3], pierde teams[4]

        // Con partido por el tercer puesto, perder la semifinal aun no da bronce.
        $this->assertSame(0, GamesEditionMedal::count());

        $this->close($stageMatches(RoundType::ThirdPlace)->sole(), 0, 1); // bronce: teams[4]
        $this->close($stageMatches(RoundType::Final)->sole(), 2, 1);      // oro: teams[2]; plata: teams[3]

        $medals = GamesEditionMedal::all()->keyBy('team_id');
        $this->assertCount(3, $medals);
        $this->assertSame(1, $medals[$teams[2]->team_id]->gold);
        $this->assertSame(1, $medals[$teams[3]->team_id]->silver);
        $this->assertSame(1, $medals[$teams[4]->team_id]->bronze);
    }
}
