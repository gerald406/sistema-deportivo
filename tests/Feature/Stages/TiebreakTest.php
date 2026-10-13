<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Enums\MatchStatus;
use App\Enums\RoundType;
use App\Enums\TiebreakType;
use App\Livewire\Results\Index as ResultsIndex;
use App\Livewire\SeasonStages\Index as SeasonStagesIndex;
use App\Models\GameMatch;
use App\Models\GamesEditionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsBracket;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * F5 (Esp. 6.2, 8.1 y 9 de las bases): un partido de llave no termina
 * empatado; se registra por que via se desempato y quien gano.
 */
class TiebreakTest extends TestCase
{
    use BuildsBracket, BuildsCompetition, CreatesActors, RefreshDatabase;

    private GameMatch $semi;

    private GameMatch $final;

    private $teams;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootCompetition();

        $this->teams = $this->entrants(4);
        $this->bracket($this->stage(), $this->teams);
        $this->semi = GameMatch::where('round_type', RoundType::SemiFinal->value)->orderBy('bracket_slot')->first();
        $this->final = GameMatch::where('round_type', RoundType::Final->value)->sole();
    }

    private function assertTiebreakError(callable $action): void
    {
        try {
            $action();
            $this->fail('Se esperaba un error de desempate.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tiebreak', $e->errors());
        }

        $this->assertSame(MatchStatus::Pending, $this->semi->fresh()->status);
    }

    public function test_tied_bracket_match_requires_a_tiebreak(): void
    {
        $this->assertTiebreakError(fn () => $this->close($this->semi, 1, 1));
        $this->assertTiebreakError(fn () => $this->close($this->semi, 1, 1, ['tiebreak_type' => 'penalties']));
        $this->assertTiebreakError(fn () => $this->close($this->semi, 1, 1, ['tiebreak_type' => 'moneda', 'tiebreak_winner_side' => 'home']));

        $this->assertSame(['home' => null, 'away' => null], $this->seats($this->final));
    }

    public function test_penalties_decide_the_winner(): void
    {
        $this->close($this->semi, 1, 1, [
            'tiebreak_type' => 'penalties', 'tiebreak_winner_side' => 'away', 'tiebreak_home' => 3, 'tiebreak_away' => 4,
        ]);

        $semi = $this->semi->fresh('participants');
        $home = $semi->participants->firstWhere('side.value', 'home');
        $away = $semi->participants->firstWhere('side.value', 'away');

        $this->assertSame(MatchStatus::Played, $semi->status);
        $this->assertSame(TiebreakType::Penalties, $semi->tiebreak_type);
        $this->assertSame([2, 3], [$home->position, $home->tiebreak_score]);
        $this->assertSame([1, 4], [$away->position, $away->tiebreak_score]);
        $this->assertSame('1.0000', $home->result_value);

        // El visitante (4.o de la lista) avanza a la final.
        $this->assertSame($this->teams[3]->id, $this->seats($this->final)['home']);
    }

    public function test_tiebreak_score_must_match_the_winner(): void
    {
        $base = ['tiebreak_type' => 'penalties', 'tiebreak_winner_side' => 'away'];

        $this->assertTiebreakError(fn () => $this->close($this->semi, 1, 1, $base + ['tiebreak_home' => 4, 'tiebreak_away' => 3]));
        $this->assertTiebreakError(fn () => $this->close($this->semi, 1, 1, $base + ['tiebreak_home' => 3, 'tiebreak_away' => 3]));
        $this->assertTiebreakError(fn () => $this->close($this->semi, 1, 1, $base + ['tiebreak_home' => 3]));
        $this->assertTiebreakError(fn () => $this->close($this->semi, 1, 1, $base + ['tiebreak_home' => -1, 'tiebreak_away' => 2]));
    }

    public function test_coin_toss_needs_no_score(): void
    {
        $this->close($this->semi, 0, 0, ['tiebreak_type' => 'coin_toss', 'tiebreak_winner_side' => 'home', 'tiebreak_home' => '', 'tiebreak_away' => '']);

        $semi = $this->semi->fresh('participants');
        $this->assertSame(TiebreakType::CoinToss, $semi->tiebreak_type);
        $this->assertSame([1, null], [$semi->participants->firstWhere('side.value', 'home')->position, $semi->participants->firstWhere('side.value', 'home')->tiebreak_score]);
        $this->assertSame($this->teams[0]->id, $this->seats($this->final)['home']);
    }

    public function test_tiebreak_is_rejected_when_not_tied(): void
    {
        $this->assertTiebreakError(fn () => $this->close($this->semi, 2, 1, ['tiebreak_type' => 'penalties', 'tiebreak_winner_side' => 'home']));
    }

    public function test_draw_is_still_allowed_outside_brackets(): void
    {
        $data = $this->buildCompetition($this->organizer);

        app(\App\Services\ResultService::class)->close($data->match, ['home' => 1, 'away' => 1], $this->organizer);

        $match = $data->match->fresh('participants');
        $this->assertSame(MatchStatus::Played, $match->status);
        $this->assertSame([1, 1], $match->participants->pluck('position')->all());
        $this->assertNull($match->tiebreak_type);

        // Y fuera de una llave no se registra desempate.
        $other = $this->buildCompetition($this->organizer);
        try {
            app(\App\Services\ResultService::class)->close($other->match, ['home' => 1, 'away' => 1, 'tiebreak_type' => 'penalties', 'tiebreak_winner_side' => 'home'], $this->organizer);
            $this->fail('Se registró un desempate fuera de una llave.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tiebreak', $e->errors());
        }
    }

    public function test_reopening_clears_the_tiebreak(): void
    {
        $this->close($this->semi, 1, 1, ['tiebreak_type' => 'free_throws', 'tiebreak_winner_side' => 'home', 'tiebreak_home' => 2, 'tiebreak_away' => 1]);

        $this->reopen($this->semi);

        $semi = $this->semi->fresh('participants');
        $this->assertSame(MatchStatus::Pending, $semi->status);
        $this->assertNull($semi->tiebreak_type);
        $this->assertSame([null, null], $semi->participants->pluck('tiebreak_score')->all());
        $this->assertSame([null, null], $semi->participants->pluck('position')->all());
        $this->assertSame(['home' => null, 'away' => null], $this->seats($this->final));
    }

    public function test_tiebreak_is_recorded_from_the_results_screen(): void
    {
        // La final aun no tiene a sus dos participantes: no se abre el formulario.
        Livewire::actingAs($this->organizer)->test(ResultsIndex::class)
            ->call('openResult', $this->final->id)
            ->assertSet('showModal', false)
            ->assertDispatched('toast', type: 'info');

        Livewire::actingAs($this->organizer)->test(ResultsIndex::class)
            ->call('openResult', $this->semi->id)
            ->assertSet('showModal', true)
            ->assertSee('Desempate')
            ->set('homeScore', '2')->set('awayScore', '2')
            ->call('saveResult')
            ->assertHasErrors('tiebreak')
            ->set('tiebreakType', 'penalties')->set('tiebreakWinner', 'home')
            ->set('tiebreakHome', '5')->set('tiebreakAway', '4')
            ->call('saveResult')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertSame(TiebreakType::Penalties, $this->semi->fresh()->tiebreak_type);
        $this->assertSame($this->teams[0]->id, $this->seats($this->final)['home']);
    }

    public function test_bracket_is_built_from_the_stages_screen(): void
    {
        // Otra competencia: Serie A con 3 delegaciones de esa serie y una de otra.
        $season = $this->headToHeadSeason();
        $serieA = GamesEditionGroup::create(['games_edition_id' => $this->edition->id, 'name' => 'Serie A']);
        $entrants = $this->entrants(4, $season);
        foreach ($entrants->take(3) as $entrant) {
            $this->registerDelegation($this->edition, $entrant->team, attributes: ['group_id' => $serieA->id]);
        }
        $series = $this->stage(['name' => 'Serie A', 'group_id' => $serieA->id, 'qualifiers' => 1, 'sort_order' => 1], $season);
        $finalStage = $this->stage(['name' => 'Fase final', 'has_third_place' => true, 'sort_order' => 2], $season);

        $component = Livewire::actingAs($this->organizer)->test(SeasonStagesIndex::class)
            ->set('seasonId', (string) $season->id)
            ->call('openBracket', $series->id);

        // Precargada solo con las delegaciones de la serie.
        $this->assertEqualsCanonicalizing($entrants->take(3)->pluck('id')->all(), $component->get('seeds'));

        $first = $component->get('seeds')[0];
        $component->call('moveSeed', 0, 1);
        $this->assertSame($first, $component->get('seeds')[1]);

        $component->call('generateBracket')->assertHasNoErrors()->assertDispatched('toast')->assertSet('showBracket', false);
        $this->assertSame(2, GameMatch::where('season_stage_id', $series->id)->count());

        // La pantalla muestra la llave y deja borrarla.
        $this->actingAs($this->organizer)->get(route('season-stages.index', ['competencia' => $season->id]))
            ->assertOk()->assertSee('Borrar llave')->assertSee('Por definir')->assertSee($entrants[0]->team->name);

        // La fase final se precarga con el ganador de la serie.
        foreach (GameMatch::where('season_stage_id', $series->id)->orderBy('bracket_round')->get() as $match) {
            $this->close($match, 2, 0);
        }
        $winner = app(\App\Services\StageService::class)->winners($series)->sole();

        $component->call('openBracket', $finalStage->id);
        $this->assertSame([$winner->id], $component->get('seeds'));

        // Con un solo participante no hay llave.
        $component->call('generateBracket')->assertHasErrors('bracket');

        $component->call('closeModal')->call('clearBracket', $series->id)->assertDispatched('toast', type: 'error');
        $this->assertSame(2, GameMatch::where('season_stage_id', $series->id)->count());
    }
}
