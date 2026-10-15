<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Enums\StageMode;
use App\Livewire\SeasonStages\Index as SeasonStagesIndex;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\SeasonStageResult;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** /fases sin competencia elegida: listado paginado y filtrable de todas las fases de la olimpiada. */
class StageListingTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private User $organizer;

    private GamesEdition $edition;

    private GamesEditionGroup $serieA;

    private Season $football;

    private Season $chess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = $this->organizer();
        $this->edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer->id]);
        $this->serieA = GamesEditionGroup::create(['games_edition_id' => $this->edition->id, 'name' => 'Serie A', 'sort_order' => 1]);

        $this->football = $this->season('Fútbol Libre Varones', 'Fútbol', 'head_to_head');
        $this->chess = $this->season('Ajedrez Libre Damas', 'Ajedrez', 'head_to_head');
    }

    private function season(string $name, string $sport, string $format): Season
    {
        return Season::factory()->create([
            'games_edition_id' => $this->edition->id,
            'name' => $name,
            'sport_id' => Sport::factory()->create(['name' => $sport, 'format_type' => $format])->id,
        ]);
    }

    private function stage(Season $season, string $name, StageMode $mode, ?GamesEditionGroup $group = null): SeasonStage
    {
        return SeasonStage::factory()->create([
            'season_id' => $season->id, 'name' => $name, 'mode' => $mode,
            'group_id' => $group?->id, 'sort_order' => $season->stages()->count() + 1,
        ]);
    }

    private function bracketMatch(SeasonStage $stage, string $status): GameMatch
    {
        $matchday = Matchday::create(['season_id' => $stage->season_id, 'name' => 'Fecha '.(Matchday::count() + 1)]);

        return GameMatch::create([
            'season_id' => $stage->season_id, 'matchday_id' => $matchday->id, 'season_stage_id' => $stage->id,
            'round_type' => 'final', 'status' => $status,
        ]);
    }

    private function listing()
    {
        return Livewire::actingAs($this->organizer)->test(SeasonStagesIndex::class);
    }

    /** @return list<string> "Competencia / Fase" de las filas visibles */
    private function rows($component): array
    {
        return collect($component->viewData('allStages')->items())
            ->map(fn (SeasonStage $s) => "{$s->season->name} / {$s->name}")
            ->all();
    }

    public function test_lists_every_stage_of_the_edition_without_choosing_a_competition(): void
    {
        $this->stage($this->football, 'Serie A', StageMode::Matches, $this->serieA);
        $this->stage($this->football, 'Fase final', StageMode::Matches);
        $this->stage($this->chess, 'Final', StageMode::DirectPodium);

        // Una fase de otra olimpiada no aparece.
        $foreign = Season::factory()->create(['name' => 'Competencia Ajena']);
        $this->stage($foreign, 'Final', StageMode::DirectPodium);

        $component = $this->listing()->assertSet('seasonId', '');

        $this->assertSame(
            ['Ajedrez Libre Damas / Final', 'Fútbol Libre Varones / Serie A', 'Fútbol Libre Varones / Fase final'],
            $this->rows($component),
        );

        $this->actingAs($this->organizer)->get(route('season-stages.index'))
            ->assertOk()->assertSee('Fútbol Libre Varones')->assertSee('Fase final')->assertDontSee('Competencia Ajena');
    }

    public function test_listing_is_paginated(): void
    {
        foreach (range(1, 12) as $i) {
            $this->stage($this->football, "Fase {$i}", StageMode::DirectPodium);
        }

        $component = $this->listing()->set('perPage', 10);
        $this->assertCount(10, $this->rows($component));
        $this->assertSame(12, $component->viewData('allStages')->total());

        // Un perPage manipulado desde el navegador no se respeta.
        $this->assertSame(15, $this->listing()->set('perPage', 100000)->viewData('allStages')->perPage());
    }

    public function test_filters_by_sport_mode_series_and_search(): void
    {
        $this->stage($this->football, 'Serie A', StageMode::Matches, $this->serieA);
        $this->stage($this->football, 'Fase final', StageMode::Matches);
        $this->stage($this->chess, 'Final', StageMode::DirectPodium);

        $this->assertSame(['Ajedrez Libre Damas / Final'], $this->rows($this->listing()->set('sportFilter', (string) $this->chess->sport_id)));
        $this->assertSame(['Ajedrez Libre Damas / Final'], $this->rows($this->listing()->set('modeFilter', 'direct_podium')));
        $this->assertSame(['Fútbol Libre Varones / Serie A'], $this->rows($this->listing()->set('groupFilter', (string) $this->serieA->id)));
        $this->assertSame(
            ['Ajedrez Libre Damas / Final', 'Fútbol Libre Varones / Fase final'],
            $this->rows($this->listing()->set('groupFilter', 'none')),
        );
        $this->assertSame(['Fútbol Libre Varones / Fase final'], $this->rows($this->listing()->set('search', 'Fase final')));

        // Valores fuera de la lista blanca no filtran.
        $this->assertCount(3, $this->rows($this->listing()->set('modeFilter', 'liga')->set('progressFilter', 'x')));
    }

    public function test_filters_by_progress(): void
    {
        $noBracket = $this->stage($this->football, 'Serie A', StageMode::Matches, $this->serieA);
        $inPlay = $this->stage($this->football, 'Serie B', StageMode::Matches);
        $finished = $this->stage($this->football, 'Fase final', StageMode::Matches);
        $noPodium = $this->stage($this->chess, 'Serie A', StageMode::DirectPodium, $this->serieA);
        $withPodium = $this->stage($this->chess, 'Final', StageMode::DirectPodium);

        $this->bracketMatch($inPlay, 'played');
        $this->bracketMatch($inPlay, 'pending');
        $this->bracketMatch($finished, 'played');
        $team = Team::factory()->create();
        $this->registerDelegation($this->edition, $team);
        SeasonStageResult::create(['season_stage_id' => $withPodium->id, 'team_id' => $team->id, 'place' => 1, 'recorded_by' => $this->organizer->id]);

        $expected = [
            'no_bracket' => $noBracket, 'in_play' => $inPlay, 'finished' => $finished,
            'no_podium' => $noPodium, 'with_podium' => $withPodium,
        ];

        foreach ($expected as $filter => $stage) {
            $rows = $this->listing()->set('progressFilter', $filter)->viewData('allStages')->items();
            $this->assertSame([$stage->id], collect($rows)->pluck('id')->all(), "Filtro {$filter}");
        }

        $component = $this->listing();
        $this->assertSame('Llave en juego (1/2 jugados)', $component->instance()->progressLabel(
            collect($component->viewData('allStages')->items())->firstWhere('id', $inPlay->id)
        ));
    }

    public function test_open_goes_to_the_competition_detail_and_back(): void
    {
        $this->stage($this->chess, 'Final', StageMode::DirectPodium);

        $this->listing()
            ->call('openSeason', $this->chess->id)
            ->assertSet('seasonId', (string) $this->chess->id)
            ->assertSee('Registrar podio')
            ->call('backToList')
            ->assertSet('seasonId', '');

        // Una competencia de otra olimpiada no se abre.
        $foreign = Season::factory()->create();
        try {
            $this->listing()->call('openSeason', $foreign->id)->assertNotFound();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_link_to_a_competition_opens_its_edition(): void
    {
        $admin = $this->admin();
        $other = GamesEdition::factory()->create(['name' => 'Otra olimpiada']);
        $season = Season::factory()->create(['games_edition_id' => $other->id, 'name' => 'Vóley de otra']);

        Livewire::withQueryParams(['competencia' => (string) $season->id])
            ->actingAs($admin)->test(SeasonStagesIndex::class)
            ->assertSet('editionId', (string) $other->id)
            ->assertSet('seasonId', (string) $season->id);
    }

    public function test_seasons_without_stages_are_listed(): void
    {
        $this->stage($this->football, 'Final', StageMode::DirectPodium);
        Season::factory()->create(['games_edition_id' => $this->edition->id, 'name' => 'Inactiva', 'is_active' => false]);

        $missing = $this->listing()->instance()->seasonsWithoutStages();

        $this->assertSame(['Ajedrez Libre Damas'], $missing->pluck('name')->all());
    }

    public function test_generate_all_only_touches_the_chosen_edition(): void
    {
        $admin = $this->admin();
        $other = GamesEdition::factory()->create(['is_active' => false]);
        $otherSeason = Season::factory()->create(['games_edition_id' => $other->id]);

        Livewire::actingAs($admin)->test(SeasonStagesIndex::class)
            ->set('editionId', (string) $this->edition->id)
            ->call('createStandardForAll')
            ->assertDispatched('toast', type: 'success');

        $this->assertTrue($this->football->stages()->exists());
        $this->assertTrue($this->chess->stages()->exists());
        $this->assertFalse($otherSeason->stages()->exists());
    }

    public function test_export_follows_the_filters(): void
    {
        $this->stage($this->football, 'Fase final', StageMode::Matches);
        $this->stage($this->chess, 'Final', StageMode::DirectPodium);

        $component = $this->listing()->set('modeFilter', 'direct_podium');
        $rows = (fn () => $this->exportRows())->call($component->instance());

        $this->assertSame(['Final'], collect($rows)->pluck('name')->all());
        $component->call('export')->assertFileDownloaded();
    }
}
