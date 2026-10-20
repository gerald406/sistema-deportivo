<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Enums\StageMode;
use App\Livewire\Public\PublicResults;
use App\Livewire\SeasonStages\Index as SeasonStagesIndex;
use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\SeasonStageResult;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Services\PublicResultsService;
use App\Services\StageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** D32: fecha, hora y sede de una fase de podio directo (atletismo, ajedrez...). */
class StageScheduleTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    private User $organizer;

    private Season $season;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer->id]);
        $this->season = Season::factory()->create([
            'games_edition_id' => $edition->id,
            'sport_id' => Sport::factory()->create(['format_type' => 'individual_ranked'])->id,
        ]);
        $this->venue = Venue::factory()->create(['name' => 'Estadio de Yunguyo']);
    }

    private function data(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Final', 'mode' => StageMode::DirectPodium->value,
            'scheduled_at' => '2026-10-29T08:30', 'venue_id' => $this->venue->id,
        ];
    }

    private function assertRejected(string $field, callable $action): void
    {
        try {
            $action();
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_organizer_schedules_a_podium_stage(): void
    {
        $stage = app(StageService::class)->register($this->season, $this->data(), $this->organizer);

        $this->assertSame('2026-10-29 08:30', $stage->fresh()->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame($this->venue->id, $stage->fresh()->venue_id);
    }

    public function test_matches_stage_ignores_the_schedule(): void
    {
        $stage = app(StageService::class)->register($this->season, $this->data(['mode' => StageMode::Matches->value]), $this->organizer);

        $this->assertNull($stage->fresh()->scheduled_at);
        $this->assertNull($stage->fresh()->venue_id);
    }

    public function test_inactive_venue_is_rejected_but_a_saved_one_is_kept(): void
    {
        $inactive = Venue::factory()->create(['is_active' => false]);
        $this->assertRejected('form.venue_id', fn () => app(StageService::class)
            ->register($this->season, $this->data(['venue_id' => $inactive->id]), $this->organizer));

        $stage = app(StageService::class)->register($this->season, $this->data(), $this->organizer);
        $this->venue->update(['is_active' => false]);
        app(StageService::class)->update($stage, $this->data(['scheduled_at' => '2026-10-30T10:00']), $this->organizer);

        $this->assertSame($this->venue->id, $stage->fresh()->venue_id);
    }

    public function test_date_outside_the_edition_is_rejected(): void
    {
        $this->assertRejected('form.scheduled_at', fn () => app(StageService::class)
            ->register($this->season, $this->data(['scheduled_at' => '2026-10-31T09:00']), $this->organizer));
        $this->assertRejected('form.scheduled_at', fn () => app(StageService::class)
            ->register($this->season, $this->data(['scheduled_at' => '2026-10-27T23:59']), $this->organizer));
    }

    public function test_delegate_cannot_schedule(): void
    {
        $stage = SeasonStage::factory()->create(['season_id' => $this->season->id, 'name' => 'Final', 'mode' => StageMode::DirectPodium]);

        $this->expectException(AuthorizationException::class);
        app(StageService::class)->update($stage, $this->data(), $this->delegate());
    }

    public function test_deleting_the_venue_leaves_the_stage_without_venue(): void
    {
        $stage = app(StageService::class)->register($this->season, $this->data(), $this->organizer);

        $this->venue->delete();

        $this->assertNull($stage->fresh()->venue_id);
    }

    public function test_stages_screen_saves_and_lists_the_schedule(): void
    {
        Livewire::actingAs($this->organizer)->test(SeasonStagesIndex::class)
            ->set('seasonId', (string) $this->season->id)
            ->call('create')
            ->set('form.name', 'Final')
            ->set('form.mode', StageMode::DirectPodium->value)
            ->set('form.scheduled_at', '2026-10-29T08:30')
            ->set('form.venue_id', (string) $this->venue->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('29/10/2026 08:30')
            ->assertSee('Estadio de Yunguyo');
    }

    public function test_public_page_shows_scheduled_podium_stage_before_its_results(): void
    {
        $this->season->update(['status' => 'active', 'is_active' => true]);
        app(StageService::class)->register($this->season, $this->data(), $this->organizer);
        SeasonStage::factory()->create(['season_id' => $this->season->id, 'name' => 'Sin programar', 'mode' => StageMode::DirectPodium]);

        Livewire::test(PublicResults::class)->call('setView', 'podios')
            ->assertSee('29/10/2026 08:30')
            ->assertSee('Estadio de Yunguyo')
            ->assertSee('Por disputar')
            ->assertDontSee('Sin programar');
    }

    public function test_public_schedule_lists_scheduled_podium_stage_until_its_podium_is_recorded(): void
    {
        $this->season->update(['status' => 'active', 'is_active' => true]);
        $stage = app(StageService::class)->register($this->season, $this->data(), $this->organizer);

        $summary = app(PublicResultsService::class)->seriesSummary($this->season->games_edition_id)[PublicResultsService::FINALS];
        $this->assertSame(1, $summary['pending']);
        $this->assertSame('2026-10-29 08:30', $summary['nextAt']->format('Y-m-d H:i'));

        $component = Livewire::test(PublicResults::class)->call('setView', 'programacion');
        $rows = $component->viewData('days')->flatMap(fn (array $day) => $day['rows']);
        $this->assertSame(['stage-'.$stage->id], $rows->pluck('id')->all());
        $component->assertSee('08:30')->assertSee('Estadio de Yunguyo')->assertSee('1 prueba');

        $team = Team::factory()->create();
        SeasonStageResult::create(['season_stage_id' => $stage->id, 'place' => 1, 'team_id' => $team->id, 'recorded_by' => $this->organizer->id]);

        $rows = Livewire::test(PublicResults::class)->call('setView', 'programacion')
            ->viewData('days')->flatMap(fn (array $day) => $day['rows']);
        $this->assertSame([], $rows->pluck('id')->all());
    }
}
