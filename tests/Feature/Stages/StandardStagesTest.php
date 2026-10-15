<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Enums\StageMode;
use App\Enums\StandardStages;
use App\Livewire\Admin\Sports\Index as SportsIndex;
use App\Livewire\SeasonStages\Index as SeasonStagesIndex;
use App\Models\EditionAward;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\Season;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Services\DirectPodiumService;
use App\Services\StageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * D18: la estructura de fases estandar es un dato del deporte. Ajedrez:
 * series de podio (sistema suizo fuera del sistema) + final de podio;
 * tenis de mesa: series en llave + fase final, como los deportes de equipo.
 */
class StandardStagesTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private GamesEdition $edition;

    private User $organizer;

    /** @var list<GamesEditionGroup> */
    private array $groups = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = $this->organizer();
        $this->edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer->id]);

        foreach (['Serie A', 'Serie B', 'Serie C', 'Serie D'] as $i => $name) {
            $this->groups[] = GamesEditionGroup::create(['games_edition_id' => $this->edition->id, 'name' => $name, 'sort_order' => $i + 1]);
        }
    }

    private function season(array $sportAttributes, array $seasonAttributes = []): Season
    {
        return Season::factory()->create($seasonAttributes + [
            'games_edition_id' => $this->edition->id,
            'sport_id' => Sport::factory()->create($sportAttributes)->id,
        ]);
    }

    private function delegationIn(GamesEditionGroup $group): Team
    {
        $team = Team::factory()->create();
        $this->registerDelegation($this->edition, $team, attributes: ['group_id' => $group->id]);

        return $team;
    }

    public function test_chess_gets_podium_series_and_a_podium_final(): void
    {
        $season = $this->season(['format_type' => 'head_to_head', 'standard_stages' => StandardStages::PodiumSeries]);

        app(StageService::class)->createStandardStages($season, $this->organizer);

        $stages = $season->stages()->with('group')->get();
        $this->assertSame(['Serie A', 'Serie B', 'Serie C', 'Serie D', 'Final'], $stages->pluck('name')->all());

        foreach ($stages->take(4) as $series) {
            $this->assertSame(StageMode::DirectPodium, $series->mode);
            $this->assertSame($series->name, $series->group->name);
            $this->assertSame(1, $series->qualifiers);
            $this->assertFalse($series->has_third_place);
        }

        $final = $stages->last();
        $this->assertSame(StageMode::DirectPodium, $final->mode);
        $this->assertNull($final->group_id);
        $this->assertFalse($final->has_third_place);
    }

    public function test_table_tennis_gets_bracket_series_and_a_final(): void
    {
        $season = $this->season(['format_type' => 'head_to_head', 'standard_stages' => StandardStages::BracketSeries]);

        app(StageService::class)->createStandardStages($season, $this->organizer);

        $stages = $season->stages()->get();
        $this->assertSame(['Serie A', 'Serie B', 'Serie C', 'Serie D', 'Fase final'], $stages->pluck('name')->all());
        $this->assertSame([StageMode::Matches], $stages->pluck('mode')->unique()->values()->all());
        $this->assertTrue($stages->last()->has_third_place);
    }

    public function test_without_setting_the_format_decides(): void
    {
        $this->assertSame(StandardStages::BracketSeries, app(StageService::class)->standardStagesFor($this->season(['format_type' => 'head_to_head'])));
        $this->assertSame(StandardStages::PodiumOnly, app(StageService::class)->standardStagesFor($this->season(['format_type' => 'individual_ranked'])));

        // El deporte manda sobre el formato.
        $this->assertSame(StandardStages::PodiumOnly, app(StageService::class)->standardStagesFor(
            $this->season(['format_type' => 'head_to_head', 'standard_stages' => StandardStages::PodiumOnly])
        ));
    }

    public function test_series_podium_only_accepts_delegations_of_that_series(): void
    {
        $season = $this->season(['format_type' => 'head_to_head', 'standard_stages' => StandardStages::PodiumSeries]);
        app(StageService::class)->createStandardStages($season, $this->organizer);
        $serieA = $season->stages()->where('name', 'Serie A')->sole();
        $own = $this->delegationIn($this->groups[0]);
        $foreign = $this->delegationIn($this->groups[2]);

        try {
            app(DirectPodiumService::class)->save($serieA, [1 => ['team_id' => $foreign->id]], $this->organizer);
            $this->fail('Se aceptó una delegación de otra serie.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('podium.1', $e->errors());
        }

        app(DirectPodiumService::class)->save($serieA, [1 => ['team_id' => $own->id]], $this->organizer);
        $this->assertSame($own->id, $serieA->results()->sole()->team_id);

        // La final (sin serie) admite a cualquiera inscrito.
        app(DirectPodiumService::class)->save($season->stages()->where('name', 'Final')->sole(), [1 => ['team_id' => $foreign->id]], $this->organizer);
        $this->addToAssertionCount(1);
    }

    public function test_podium_screen_lists_only_the_series_delegations(): void
    {
        $season = $this->season(['format_type' => 'head_to_head', 'standard_stages' => StandardStages::PodiumSeries]);
        app(StageService::class)->createStandardStages($season, $this->organizer);
        $own = $this->delegationIn($this->groups[0]);
        $foreign = $this->delegationIn($this->groups[1]);

        $component = Livewire::actingAs($this->organizer)->test(SeasonStagesIndex::class)
            ->set('seasonId', (string) $season->id)
            ->call('openPodium', $season->stages()->where('name', 'Serie A')->value('id'));

        $this->assertSame([$own->id], $component->instance()->teamOptions()->pluck('id')->all());

        $component->call('openPodium', $season->stages()->where('name', 'Final')->value('id'));
        $this->assertEqualsCanonicalizing([$own->id, $foreign->id], $component->instance()->teamOptions()->pluck('id')->all());
    }

    public function test_series_podiums_do_not_score(): void
    {
        $season = $this->season(['format_type' => 'head_to_head', 'standard_stages' => StandardStages::PodiumSeries]);
        app(StageService::class)->createStandardStages($season, $this->organizer);
        $team = $this->delegationIn($this->groups[0]);

        app(DirectPodiumService::class)->save($season->stages()->where('name', 'Serie A')->sole(), [1 => ['team_id' => $team->id]], $this->organizer);
        $this->assertSame(0, EditionAward::count());

        app(DirectPodiumService::class)->save($season->stages()->where('name', 'Final')->sole(), [1 => ['team_id' => $team->id]], $this->organizer);
        $this->assertSame(1, EditionAward::where('season_id', $season->id)->where('place', 1)->count());
    }

    public function test_one_button_creates_the_stages_of_every_competition(): void
    {
        $chess = $this->season(['format_type' => 'head_to_head', 'standard_stages' => StandardStages::PodiumSeries]);
        $football = $this->season(['format_type' => 'head_to_head']);
        $athletics = $this->season(['format_type' => 'individual_ranked']);
        $inactive = $this->season(['format_type' => 'individual_ranked'], ['is_active' => false]);
        $closed = $this->season(['format_type' => 'individual_ranked'], ['status' => 'closed']);
        $foreign = Season::factory()->create(['games_edition_id' => GamesEdition::factory()->create()->id]);

        // Una que ya tiene fases no se toca.
        app(StageService::class)->register($athletics, ['name' => 'Mi fase', 'mode' => 'direct_podium'], $this->organizer);

        Livewire::actingAs($this->organizer)->test(SeasonStagesIndex::class)
            ->call('createStandardForAll')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame(5, $chess->stages()->count());
        $this->assertSame(5, $football->stages()->count());

        // El boton respeta las fases estandar del deporte (ajedrez: podio), no solo el formato.
        $this->assertSame([StageMode::DirectPodium], $chess->stages()->pluck('mode')->unique()->values()->all());
        $this->assertSame([StageMode::Matches], $football->stages()->pluck('mode')->unique()->values()->all());
        $this->assertSame(['Mi fase'], $athletics->stages()->pluck('name')->all());
        $this->assertSame(0, $inactive->stages()->count());
        $this->assertSame(0, $closed->stages()->count());
        $this->assertSame(0, $foreign->stages()->count());

        // Repetirlo no crea nada.
        Livewire::actingAs($this->organizer)->test(SeasonStagesIndex::class)
            ->call('createStandardForAll')
            ->assertDispatched('toast', type: 'info');
        $this->assertSame(5, $chess->stages()->count());
    }

    public function test_button_is_shown_without_choosing_a_competition(): void
    {
        $this->actingAs($this->organizer)->get(route('season-stages.index'))
            ->assertOk()
            ->assertSee('Generar fases de todas las competencias')
            ->assertSee('$wire.createStandardForAll()', false);

        // Sin competencias activas lo dice, en vez de "ya tenian fases".
        Livewire::actingAs($this->organizer)->test(SeasonStagesIndex::class)
            ->call('createStandardForAll')
            ->assertDispatched('toast', type: 'info', message: 'No hay competencias activas: genéralas primero en Competencias.');
    }

    public function test_admin_sets_standard_stages_on_the_sport(): void
    {
        $sport = Sport::factory()->create(['format_type' => 'head_to_head']);

        Livewire::actingAs($this->admin())->test(SportsIndex::class)
            ->call('editSport', $sport->id)
            ->assertSet('sportForm.standard_stages', '')
            ->set('sportForm.standard_stages', 'podium_series')
            ->call('saveSport')
            ->assertHasNoErrors();
        $this->assertSame(StandardStages::PodiumSeries, $sport->refresh()->standard_stages);

        Livewire::actingAs($this->admin())->test(SportsIndex::class)
            ->call('editSport', $sport->id)
            ->set('sportForm.standard_stages', '')
            ->call('saveSport')
            ->assertHasNoErrors();
        $this->assertNull($sport->refresh()->standard_stages);

        Livewire::actingAs($this->admin())->test(SportsIndex::class)
            ->call('editSport', $sport->id)
            ->set('sportForm.standard_stages', 'liga')
            ->call('saveSport')
            ->assertHasErrors('sportForm.standard_stages');
    }
}
