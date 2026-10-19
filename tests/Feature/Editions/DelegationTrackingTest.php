<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Livewire\Reports\DelegationTracking;
use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Services\DelegationTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** Seguimiento de delegaciones: totales por delegacion y plantel por disciplina, acotados por rol. */
class DelegationTrackingTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private User $organizer;

    private User $delegate;

    private GamesEdition $edition;

    private Team $team;

    private Team $other;

    private Season $football;

    private Season $chess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = $this->organizer();
        $this->edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer->id, 'start_date' => '2026-10-28']);
        $this->delegate = $this->delegate();
        $this->team = Team::factory()->create(['name' => 'IESP Yunguyo']);
        $this->other = Team::factory()->create(['name' => 'ESFA Puno']);
        $this->registerDelegation($this->edition, $this->team, $this->delegate);
        $this->registerDelegation($this->edition, $this->other, $this->delegate());

        $this->football = $this->season('Fútbol Libre Varones');
        $this->chess = $this->season('Ajedrez Libre Varones');
    }

    private function season(string $name): Season
    {
        return Season::factory()->create([
            'games_edition_id' => $this->edition->id, 'sport_id' => Sport::factory()->create()->id,
            'name' => $name, 'is_active' => true,
        ]);
    }

    private function enroll(Season $season, Team $team): SeasonTeam
    {
        return SeasonTeam::create(['season_id' => $season->id, 'team_id' => $team->id, 'is_active' => true]);
    }

    private function service(): DelegationTrackingService
    {
        return app(DelegationTrackingService::class);
    }

    private function row(User $actor, Team $team): ?object
    {
        return $this->service()->summary($actor, $this->edition)->first(fn ($r) => $r->entry->team_id === $team->id);
    }

    public function test_summary_counts_distinct_people_and_disciplines(): void
    {
        $football = $this->enroll($this->football, $this->team);
        $chess = $this->enroll($this->chess, $this->team);

        $man = $this->rosterEntry($football, 9, ['gender' => 'M']);
        $this->rosterEntry($football, 10, ['gender' => 'F']);
        // La misma persona en otra disciplina cuenta una sola vez.
        SeasonTeamPlayer::create(['season_team_id' => $chess->id, 'player_id' => $man->player_id, 'status' => 'active', 'enrolled_at' => '2026-10-20']);
        // De baja: no cuenta.
        $this->rosterEntry($football, 11)->update(['status' => 'inactive']);

        $row = $this->row($this->organizer, $this->team);

        $this->assertSame(2, $row->people);
        $this->assertSame(1, $row->men);
        $this->assertSame(1, $row->women);
        $this->assertSame(2, $row->disciplines);
        $this->assertSame(2, $row->withRoster);
        $this->assertSame(0, $row->empty);
    }

    public function test_enrolled_discipline_without_roster_is_reported_as_empty(): void
    {
        $this->enroll($this->football, $this->team);
        $this->enroll($this->chess, $this->team)->update(['is_active' => false]);

        $row = $this->row($this->organizer, $this->team);

        $this->assertSame(1, $row->disciplines);
        $this->assertSame(1, $row->empty);
        $this->assertSame(0, $row->people);
    }

    public function test_organizer_sees_all_delegations_and_delegate_only_its_own(): void
    {
        $this->assertCount(2, $this->service()->summary($this->organizer, $this->edition));
        $this->assertSame([$this->team->id], $this->service()->summary($this->delegate, $this->edition)->pluck('entry.team_id')->all());

        $this->assertFalse($this->service()->canView($this->delegate, $this->edition, $this->other));
        $this->assertTrue($this->service()->canView($this->organizer, $this->edition, $this->other));

        // Otro organizador no ve una olimpiada ajena.
        $this->assertCount(0, $this->service()->summary($this->organizer(), $this->edition));
    }

    public function test_detail_lists_each_discipline_with_its_roster(): void
    {
        $football = $this->enroll($this->football, $this->team);
        $this->enroll($this->chess, $this->team);
        $this->rosterEntry($football, 7, ['birth_date' => '1990-05-01']);

        $detail = $this->service()->detail($this->edition, $this->team)->keyBy(fn ($d) => $d->seasonTeam->season_id);

        $this->assertSame(1, $detail[$this->football->id]->count);
        $this->assertSame(36, $detail[$this->football->id]->players->first()->age);
        $this->assertSame(0, $detail[$this->chess->id]->count);
    }

    public function test_screen_rejects_a_foreign_delegation_from_the_url(): void
    {
        $this->enroll($this->football, $this->other);

        $screen = Livewire::actingAs($this->delegate)->test(DelegationTracking::class)
            ->assertSet('editionId', (string) $this->edition->id)
            ->assertSee('IESP Yunguyo')
            ->assertDontSee('ESFA Puno');

        $screen->set('teamId', (string) $this->other->id);
        $this->assertNull($screen->instance()->team());

        Livewire::actingAs($this->organizer)->test(DelegationTracking::class)
            ->call('show', $this->other->id)
            ->assertSee('Fútbol Libre Varones');
    }
}
