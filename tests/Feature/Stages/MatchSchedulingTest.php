<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Livewire\Matches\Index as MatchesIndex;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\SeasonTeam;
use App\Models\Venue;
use App\Services\GameMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsBracket;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** D24: /partidos solo programa (jornada, fecha, sede, estado); los participantes vienen de la llave. */
class MatchSchedulingTest extends TestCase
{
    use BuildsBracket, BuildsCompetition, CreatesActors, RefreshDatabase;

    private GameMatch $match;

    private SeasonTeam $home;

    private SeasonTeam $away;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootCompetition();
        [$this->home, $this->away] = $this->entrants(2)->all();

        $matchday = Matchday::create(['season_id' => $this->season->id, 'name' => 'Fecha 1']);
        $this->match = GameMatch::create(['season_id' => $this->season->id, 'matchday_id' => $matchday->id, 'round_type' => 'final', 'status' => 'pending']);

        foreach (['home' => $this->home, 'away' => $this->away] as $side => $seasonTeam) {
            EventParticipant::create(['match_id' => $this->match->id, 'participant_type' => 'season_team', 'participant_id' => $seasonTeam->id, 'side' => $side]);
        }
    }

    private function participants(): array
    {
        return $this->match->participants()->orderBy('id')->get(['participant_type', 'participant_id', 'side'])
            ->map(fn ($p) => [$p->participant_type, $p->participant_id, $p->side?->value])->all();
    }

    public function test_modal_only_schedules_and_keeps_the_participants(): void
    {
        $before = $this->participants();
        $second = Matchday::create(['season_id' => $this->season->id, 'name' => 'Fecha 2']);
        $venue = Venue::factory()->create(['name' => 'Estadio Yunguyo', 'is_active' => true]);

        Livewire::actingAs($this->organizer)->test(MatchesIndex::class)
            ->call('edit', $this->match->id)
            ->assertSee($this->home->team->name)
            ->assertDontSee('Carril')
            ->set('form.matchday_id', $second->id)
            ->set('form.venue_id', $venue->id)
            ->set('form.scheduled_at', '2026-10-28T09:30')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $this->match->refresh();
        $this->assertSame([$second->id, $venue->id, '2026-10-28 09:30'], [$this->match->matchday_id, $this->match->venue_id, $this->match->scheduled_at->format('Y-m-d H:i')]);
        $this->assertSame('final', $this->match->round_type->value);
        $this->assertSame($before, $this->participants());
    }

    public function test_schedule_ignores_what_comes_from_the_bracket(): void
    {
        app(GameMatchService::class)->schedule($this->match, [
            'matchday_id' => $this->match->matchday_id, 'round_type' => 'regular', 'discipline_id' => 999, 'status' => 'canceled',
        ], $this->organizer);

        $this->match->refresh();
        $this->assertSame('final', $this->match->round_type->value);
        $this->assertSame('canceled', $this->match->status->value);
        $this->assertCount(2, $this->participants());
    }

    public function test_result_status_is_not_changed_from_here(): void
    {
        $this->match->update(['status' => 'played']);

        Livewire::actingAs($this->organizer)->test(MatchesIndex::class)
            ->call('edit', $this->match->id)
            ->set('form.status', 'pending')
            ->call('save')
            ->assertHasErrors('form.status');

        $this->assertSame('played', $this->match->refresh()->status->value);
    }

    public function test_screen_does_not_create_loose_matches(): void
    {
        $this->actingAs($this->organizer)->get(route('matches.index'))
            ->assertOk()
            ->assertSee('Generar llaves en Fases')
            ->assertDontSee('wire:click="create"', false);

        $this->assertFalse(method_exists(MatchesIndex::class, 'create'));
    }

    public function test_foreign_organizer_cannot_schedule(): void
    {
        try {
            Livewire::actingAs($this->organizer())->test(MatchesIndex::class)->call('edit', $this->match->id)->assertNotFound();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }
}
