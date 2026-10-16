<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Livewire\EditionGroups\Index as GroupsIndex;
use App\Livewire\EditionTeams\Index as EditionTeamsIndex;
use App\Models\GamesEditionGroup;
use App\Models\GamesEditionTeam;
use App\Models\SeasonStageResult;
use App\Models\SeasonTeam;
use App\Services\EditionTeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsBracket;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** D28: la serie de una delegacion se cambia en Delegaciones inscritas; /series lleva ahi y se avisa si ya esta en una llave. */
class GroupAssignmentTest extends TestCase
{
    use BuildsBracket, BuildsCompetition, CreatesActors, RefreshDatabase;

    private GamesEditionGroup $groupA;

    private GamesEditionGroup $groupB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootCompetition();
        $this->groupA = GamesEditionGroup::create(['games_edition_id' => $this->edition->id, 'name' => 'Serie A', 'sort_order' => 1]);
        $this->groupB = GamesEditionGroup::create(['games_edition_id' => $this->edition->id, 'name' => 'Serie B', 'sort_order' => 2]);
    }

    /** @return array{0: GamesEditionTeam, 1: SeasonTeam} dos delegaciones de la Serie A con su llave generada; devuelve la primera */
    private function bracketInGroupA(): array
    {
        $entrants = $this->entrants(2);
        GamesEditionTeam::whereIn('team_id', $entrants->pluck('team_id'))->update(['group_id' => $this->groupA->id]);
        $this->bracket($this->stage(['name' => 'Serie A', 'group_id' => $this->groupA->id]), $entrants);

        return [GamesEditionTeam::where('team_id', $entrants[0]->team_id)->sole(), $entrants[0]];
    }

    private function teamsScreen()
    {
        return Livewire::withQueryParams(['olimpiada' => (string) $this->edition->id])
            ->actingAs($this->organizer)->test(EditionTeamsIndex::class);
    }

    public function test_series_screen_links_each_count_to_its_delegations(): void
    {
        Livewire::withQueryParams(['olimpiada' => (string) $this->edition->id])
            ->actingAs($this->organizer)->test(GroupsIndex::class)
            ->assertSee('Delegaciones inscritas')
            // assertSee escapa: en el HTML el & del enlace va como &amp;.
            ->assertSee(route('edition-teams.index', ['olimpiada' => $this->edition->id, 'serie' => $this->groupA->id]));
    }

    public function test_delegations_screen_reads_the_series_from_the_url(): void
    {
        [$inA] = $this->bracketInGroupA();
        $other = $this->entrants(1)->first();

        $screen = Livewire::withQueryParams(['olimpiada' => (string) $this->edition->id, 'serie' => (string) $this->groupA->id])
            ->actingAs($this->organizer)->test(EditionTeamsIndex::class)
            ->assertSet('groupFilter', (string) $this->groupA->id);

        $listed = $screen->viewData('entries')->pluck('team_id');
        $this->assertContains($inA->team_id, $listed);
        $this->assertNotContains($other->team_id, $listed);
    }

    public function test_moving_a_delegation_already_in_a_bracket_warns_but_moves_it(): void
    {
        [$entry] = $this->bracketInGroupA();

        $screen = $this->teamsScreen()->call('edit', $entry->id);
        $this->assertSame([], $screen->instance()->groupChangeWarning());

        $screen->set('form.group_id', (string) $this->groupB->id);
        $this->assertSame([$this->season->name.' — Serie A'], $screen->instance()->groupChangeWarning());
        $screen->assertSee('ya está en la llave o el podio');

        $screen->call('save')->assertHasNoErrors()->assertDispatched('toast', type: 'warning');
        $this->assertSame($this->groupB->id, $entry->refresh()->group_id);
    }

    public function test_moving_a_delegation_without_bracket_is_silent(): void
    {
        $seasonTeam = $this->entrants(1)->first();
        $entry = GamesEditionTeam::where('team_id', $seasonTeam->team_id)->sole();
        $entry->update(['group_id' => $this->groupA->id]);

        $this->teamsScreen()->call('edit', $entry->id)
            ->set('form.group_id', (string) $this->groupB->id)
            ->call('save')
            ->assertDispatched('toast', type: 'success');
        $this->assertSame($this->groupB->id, $entry->refresh()->group_id);
    }

    public function test_podium_of_the_series_also_counts(): void
    {
        $seasonTeam = $this->entrants(1)->first();
        $entry = GamesEditionTeam::where('team_id', $seasonTeam->team_id)->sole();
        $entry->update(['group_id' => $this->groupA->id]);
        $stage = $this->stage(['name' => 'Serie A', 'group_id' => $this->groupA->id, 'mode' => 'direct_podium']);
        SeasonStageResult::create(['season_stage_id' => $stage->id, 'place' => 1, 'team_id' => $seasonTeam->team_id, 'recorded_by' => $this->organizer->id]);

        $this->assertSame([$this->season->name.' — Serie A'], app(EditionTeamService::class)->stagesUsingGroup($entry)->all());
    }
}
