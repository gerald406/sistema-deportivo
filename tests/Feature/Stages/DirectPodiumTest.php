<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Enums\StageMode;
use App\Livewire\SeasonStages\Index as SeasonStagesIndex;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\SeasonStageResult;
use App\Models\SeasonTeam;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Services\DirectPodiumService;
use App\Services\StageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** F5 (D13, Art. 8 y 10): fases de una competencia y podio directo. */
class DirectPodiumTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private GamesEdition $edition;

    private User $organizer;

    /** @var list<Team> */
    private array $teams = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = $this->organizer();
        $this->edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer->id]);

        foreach (range(1, 4) as $i) {
            $this->teams[] = $team = Team::factory()->create();
            $this->registerDelegation($this->edition, $team);
        }
    }

    private function season(string $format = 'individual_ranked'): Season
    {
        return Season::factory()->create([
            'games_edition_id' => $this->edition->id,
            'sport_id' => Sport::factory()->create(['format_type' => $format])->id,
        ]);
    }

    private function podiumStage(?Season $season = null): SeasonStage
    {
        return SeasonStage::factory()->create([
            'season_id' => ($season ?? $this->season())->id, 'name' => 'Final', 'mode' => StageMode::DirectPodium,
        ]);
    }

    private function save(SeasonStage $stage, array $places, ?User $actor = null): void
    {
        app(DirectPodiumService::class)->save($stage, $places, $actor ?? $this->organizer);
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_standard_stages_for_ranked_sport(): void
    {
        $season = $this->season('individual_ranked');

        $stages = app(StageService::class)->createStandardStages($season, $this->organizer);

        $this->assertCount(1, $stages);
        $this->assertSame('Final', $stages[0]->name);
        $this->assertSame(StageMode::DirectPodium, $stages[0]->mode);
        $this->assertFalse($stages[0]->has_third_place);
    }

    public function test_standard_stages_for_head_to_head_sport(): void
    {
        foreach (['Serie A', 'Serie B', 'Serie C', 'Serie D'] as $i => $name) {
            GamesEditionGroup::create(['games_edition_id' => $this->edition->id, 'name' => $name, 'sort_order' => $i + 1]);
        }
        $season = $this->season('head_to_head');

        app(StageService::class)->createStandardStages($season, $this->organizer);

        $stages = $season->stages()->with('group')->get();
        $this->assertSame(['Serie A', 'Serie B', 'Serie C', 'Serie D', 'Fase final'], $stages->pluck('name')->all());

        foreach ($stages->take(4) as $series) {
            $this->assertSame(StageMode::Matches, $series->mode);
            $this->assertSame($series->name, $series->group->name);
            $this->assertSame(1, $series->qualifiers);
            $this->assertFalse($series->has_third_place);
        }

        $final = $stages->last();
        $this->assertSame(StageMode::Matches, $final->mode);
        $this->assertNull($final->group_id);
        $this->assertTrue($final->has_third_place);
    }

    public function test_standard_stages_are_not_duplicated(): void
    {
        $season = $this->season();
        $service = app(StageService::class);

        $service->createStandardStages($season, $this->organizer);
        $second = $service->createStandardStages($season, $this->organizer);

        $this->assertCount(0, $second);
        $this->assertSame(1, $season->stages()->count());
    }

    public function test_manager_records_a_podium(): void
    {
        $stage = $this->podiumStage();

        $this->save($stage, [
            1 => ['team_id' => $this->teams[0]->id],
            2 => ['team_id' => $this->teams[1]->id],
            3 => ['team_id' => $this->teams[2]->id],
        ]);

        $rows = $stage->results()->get();
        $this->assertSame([1, 2, 3], $rows->pluck('place')->all());
        $this->assertSame([$this->teams[0]->id, $this->teams[1]->id, $this->teams[2]->id], $rows->pluck('team_id')->all());
        $this->assertSame([$this->organizer->id], $rows->pluck('recorded_by')->unique()->all());
    }

    public function test_saving_again_replaces_the_podium(): void
    {
        $stage = $this->podiumStage();
        $this->save($stage, [1 => ['team_id' => $this->teams[0]->id], 2 => ['team_id' => $this->teams[1]->id]]);

        $this->save($stage, [1 => ['team_id' => $this->teams[3]->id]]);

        $this->assertSame([[1, $this->teams[3]->id]], $stage->results()->get()->map(fn ($r) => [$r->place, $r->team_id])->all());

        // Los tres puestos vacios borran el podio.
        $this->save($stage, [1 => [['team_id' => '']], 2 => [], 3 => []]);
        $this->assertSame(0, SeasonStageResult::count());
    }

    public function test_shared_place_is_allowed(): void
    {
        $stage = $this->podiumStage();

        $this->save($stage, [
            1 => ['team_id' => $this->teams[0]->id],
            2 => ['team_id' => $this->teams[1]->id],
            3 => [['team_id' => $this->teams[2]->id], ['team_id' => $this->teams[3]->id]],
        ]);

        $this->assertSame(2, $stage->results()->where('place', 3)->count());
        $this->assertSame(4, $stage->results()->count());
    }

    public function test_same_delegation_twice_is_rejected_unless_with_different_participants(): void
    {
        $season = $this->season();
        $stage = $this->podiumStage($season);
        $team = $this->teams[0];
        $seasonTeam = SeasonTeam::create(['season_id' => $season->id, 'team_id' => $team->id, 'is_active' => true]);
        $a = $this->rosterEntry($seasonTeam);
        $b = $this->rosterEntry($seasonTeam);

        $this->assertRejected(fn () => $this->save($stage, [1 => ['team_id' => $team->id], 2 => ['team_id' => $team->id]]));
        $this->assertRejected(fn () => $this->save($stage, [1 => [['team_id' => $team->id], ['team_id' => $team->id]]]));
        $this->assertRejected(fn () => $this->save($stage, [
            1 => ['team_id' => $team->id, 'season_team_player_id' => $a->id],
            2 => ['team_id' => $team->id, 'season_team_player_id' => $a->id],
        ]));

        $this->save($stage, [
            1 => ['team_id' => $team->id, 'season_team_player_id' => $a->id],
            2 => ['team_id' => $team->id, 'season_team_player_id' => $b->id],
        ]);
        $this->assertSame(2, $stage->results()->count());
    }

    public function test_team_must_be_registered_in_the_edition(): void
    {
        $stage = $this->podiumStage();
        $outsider = Team::factory()->create();
        $disqualified = Team::factory()->create();
        $this->registerDelegation($this->edition, $disqualified, attributes: ['status' => 'disqualified']);

        $this->assertRejected(fn () => $this->save($stage, [1 => ['team_id' => $outsider->id]]));
        $this->assertRejected(fn () => $this->save($stage, [1 => ['team_id' => $disqualified->id]]));
        $this->assertRejected(fn () => $this->save($stage, [4 => ['team_id' => $this->teams[0]->id]]));

        $this->assertSame(0, SeasonStageResult::count());
    }

    public function test_player_must_belong_to_that_team_roster(): void
    {
        $season = $this->season();
        $stage = $this->podiumStage($season);
        $own = $this->rosterEntry(SeasonTeam::create(['season_id' => $season->id, 'team_id' => $this->teams[0]->id, 'is_active' => true]));
        $other = $this->rosterEntry(SeasonTeam::create(['season_id' => $season->id, 'team_id' => $this->teams[1]->id, 'is_active' => true]));

        // Participante de otra delegacion, y de la misma delegacion en otra competencia.
        $elsewhere = $this->rosterEntry(SeasonTeam::create(['season_id' => $this->season()->id, 'team_id' => $this->teams[0]->id, 'is_active' => true]));

        $this->assertRejected(fn () => $this->save($stage, [1 => ['team_id' => $this->teams[0]->id, 'season_team_player_id' => $other->id]]));
        $this->assertRejected(fn () => $this->save($stage, [1 => ['team_id' => $this->teams[0]->id, 'season_team_player_id' => $elsewhere->id]]));

        $this->save($stage, [1 => ['team_id' => $this->teams[0]->id, 'season_team_player_id' => $own->id]]);
        $this->assertSame($own->id, $stage->results()->sole()->season_team_player_id);
    }

    public function test_podium_is_rejected_on_a_matches_stage(): void
    {
        $stage = SeasonStage::factory()->create(['season_id' => $this->season('head_to_head')->id, 'mode' => StageMode::Matches]);

        $this->assertRejected(fn () => $this->save($stage, [1 => ['team_id' => $this->teams[0]->id]]));
    }

    public function test_only_edition_manager_records(): void
    {
        $stage = $this->podiumStage();
        $delegate = $this->delegate();
        $this->registerDelegation($this->edition, $this->teams[0], $delegate);

        foreach ([$delegate, $this->organizer()] as $stranger) {
            try {
                $this->save($stage, [1 => ['team_id' => $this->teams[0]->id]], $stranger);
                $this->fail('Un usuario ajeno registró un podio.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }

            try {
                app(StageService::class)->createStandardStages($this->season(), $stranger);
                $this->fail('Un usuario ajeno creó fases.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, SeasonStageResult::count());

        $this->save($stage, [1 => ['team_id' => $this->teams[0]->id]], $this->admin());
        $this->assertSame(1, SeasonStageResult::count());
    }

    public function test_stage_with_podium_is_not_deleted(): void
    {
        $stage = $this->podiumStage();
        $this->save($stage, [1 => ['team_id' => $this->teams[0]->id]]);
        $service = app(StageService::class);

        $this->assertIsString($service->delete($stage, $this->organizer));
        $this->assertDatabaseHas('season_stages', ['id' => $stage->id]);

        // Tampoco cambia de modo con un podio registrado.
        $this->assertRejected(fn () => $service->update($stage, ['name' => 'Final', 'mode' => 'matches'], $this->organizer));

        $this->save($stage, []);
        $this->assertTrue($service->delete($stage, $this->organizer));
    }

    public function test_stage_rules(): void
    {
        $season = $this->season('head_to_head');
        $service = app(StageService::class);
        $foreignGroup = GamesEditionGroup::create(['games_edition_id' => GamesEdition::factory()->create()->id, 'name' => 'Serie Ajena']);

        $stage = $service->register($season, ['name' => ' Serie A ', 'mode' => 'matches', 'qualifiers' => 1, 'has_third_place' => true], $this->organizer);
        $this->assertSame('Serie A', $stage->name);

        $this->assertRejected(fn () => $service->register($season, ['name' => 'Serie A', 'mode' => 'matches'], $this->organizer));
        $this->assertRejected(fn () => $service->register($season, ['name' => 'Otra', 'mode' => 'liga'], $this->organizer));
        $this->assertRejected(fn () => $service->register($season, ['name' => 'Otra', 'mode' => 'matches', 'group_id' => $foreignGroup->id], $this->organizer));

        // Sin historial el organizador cambia el modo (ajedrez a podio directo, D14).
        $service->update($stage, ['name' => 'Serie A', 'mode' => 'direct_podium', 'has_third_place' => true], $this->organizer);
        $this->assertSame(StageMode::DirectPodium, $stage->refresh()->mode);
        $this->assertFalse($stage->has_third_place);
    }

    public function test_podium_is_recorded_from_the_screen(): void
    {
        $season = $this->season();

        $component = Livewire::actingAs($this->organizer)->test(SeasonStagesIndex::class)
            ->set('seasonId', (string) $season->id)
            ->call('createStandard')
            ->assertDispatched('toast');

        $stage = $season->stages()->sole();

        $component->call('openPodium', $stage->id)
            ->set('podium.1.0.team_id', $this->teams[0]->id)
            ->set('podium.2.0.team_id', $this->teams[1]->id)
            ->call('addPodiumRow', 3)
            ->set('podium.3.0.team_id', $this->teams[2]->id)
            ->set('podium.3.1.team_id', $this->teams[3]->id)
            ->call('savePodium')
            ->assertHasNoErrors();

        $this->assertSame(4, $stage->results()->count());

        $this->actingAs($this->organizer)->get(route('season-stages.index', ['competencia' => $season->id]))
            ->assertOk()->assertSee($this->teams[0]->name)->assertSee('Final');
    }

    public function test_screen_is_closed_to_delegates_and_foreign_competitions(): void
    {
        $season = $this->season();
        $stage = $this->podiumStage($season);

        $this->actingAs($this->delegate())->get(route('season-stages.index'))->assertForbidden();

        // Un organizador ajeno no alcanza la competencia ni su fase.
        $stranger = $this->organizer();
        $this->actingAs($stranger)->get(route('season-stages.index', ['competencia' => $season->id]))->assertOk()->assertDontSee($season->name);

        try {
            Livewire::actingAs($stranger)->test(SeasonStagesIndex::class)
                ->set('seasonId', (string) $season->id)
                ->call('openPodium', $stage->id)
                ->assertNotFound();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }
}
