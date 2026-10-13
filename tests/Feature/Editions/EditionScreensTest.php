<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Livewire\EditionGroups\Index as EditionGroupsIndex;
use App\Livewire\EditionTeams\Index as EditionTeamsIndex;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\GamesEditionTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * F2: las acciones publicas de las pantallas de series y delegaciones
 * inscritas (invocables desde el navegador) respetan la olimpiada elegida
 * y el rol.
 */
class EditionScreensTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    /** Un id fuera del alcance del usuario no existe para el: 404. */
    private function assertOutOfReach(callable $action): void
    {
        // findOrFail() lanza ModelNotFoundException; abort(404) llega como
        // respuesta 404 del componente. Ambos son el mismo resultado.
        try {
            $action()->assertNotFound();
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_organizer_creates_a_group_from_the_screen(): void
    {
        $organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);

        Livewire::actingAs($organizer)->test(EditionGroupsIndex::class)
            ->assertSet('editionId', (string) $edition->id)
            ->call('create')
            ->set('form.name', 'Serie A')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('games_edition_groups', ['games_edition_id' => $edition->id, 'name' => 'Serie A', 'sort_order' => 1]);
    }

    public function test_group_of_a_foreign_edition_cannot_be_edited_or_deleted_from_the_screen(): void
    {
        $organizer = $this->organizer();
        GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $foreign = GamesEdition::factory()->create();
        $foreignGroup = GamesEditionGroup::create(['games_edition_id' => $foreign->id, 'name' => 'Serie Ajena']);

        // Con su propia olimpiada elegida, el id ajeno no existe para el.
        $this->assertOutOfReach(fn () => Livewire::actingAs($organizer)->test(EditionGroupsIndex::class)
            ->call('delete', $foreignGroup->id));

        // Forzando la olimpiada ajena en la propiedad publica tampoco.
        $this->assertOutOfReach(fn () => Livewire::actingAs($organizer)->test(EditionGroupsIndex::class)
            ->set('editionId', (string) $foreign->id)
            ->call('edit', $foreignGroup->id));

        $this->assertDatabaseHas('games_edition_groups', ['id' => $foreignGroup->id]);
    }

    public function test_organizer_registers_a_delegation_from_the_screen(): void
    {
        $organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $group = GamesEditionGroup::create(['games_edition_id' => $edition->id, 'name' => 'Serie A']);
        $team = Team::factory()->create(['created_by' => $organizer->id]);
        $titular = $this->delegate();

        Livewire::actingAs($organizer)->test(EditionTeamsIndex::class)
            ->call('create')
            ->set('form.team_id', $team->id)
            ->set('form.group_id', $group->id)
            ->set('form.delegate_id', $titular->id)
            ->set('form.fee_paid', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('games_edition_teams', [
            'games_edition_id' => $edition->id, 'team_id' => $team->id, 'group_id' => $group->id,
            'delegate_id' => $titular->id, 'substitute_delegate_id' => null, 'fee_paid' => true, 'status' => 'registered',
        ]);
    }

    public function test_service_validation_errors_reach_the_form(): void
    {
        $organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $team = Team::factory()->create(['created_by' => $organizer->id]);
        $delegate = $this->delegate();

        Livewire::actingAs($organizer)->test(EditionTeamsIndex::class)
            ->call('create')
            ->set('form.team_id', $team->id)
            ->set('form.delegate_id', $delegate->id)
            ->set('form.substitute_delegate_id', $delegate->id)
            ->call('save')
            ->assertHasErrors('form.substitute_delegate_id');

        $this->assertSame(0, GamesEditionTeam::where('games_edition_id', $edition->id)->count());
    }

    public function test_organizer_creates_a_delegate_account_from_the_screen(): void
    {
        $organizer = $this->organizer();
        GamesEdition::factory()->create(['organizer_id' => $organizer->id]);

        $component = Livewire::actingAs($organizer)->test(EditionTeamsIndex::class)
            ->call('create')
            ->call('toggleCreatingDelegate')
            ->set('newDelegate.name', 'Delegada Nueva')
            ->set('newDelegate.email', 'delegada.nueva@example.com')
            ->set('newDelegate.password', 'secreto123')
            ->call('saveNewDelegateAccount')
            ->assertHasNoErrors();

        $created = User::where('email', 'delegada.nueva@example.com')->firstOrFail();
        $this->assertSame(['delegado'], $created->getRoleNames()->all());
        $component->assertSet('form.delegate_id', $created->id)->assertSet('creatingDelegate', false);
    }

    public function test_delegation_of_a_foreign_edition_cannot_be_touched_from_the_screen(): void
    {
        $organizer = $this->organizer();
        GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $foreignEntry = $this->registerDelegation(GamesEdition::factory()->create(), Team::factory()->create());

        $this->assertOutOfReach(fn () => Livewire::actingAs($organizer)->test(EditionTeamsIndex::class)
            ->call('delete', $foreignEntry->id));

        $this->assertOutOfReach(fn () => Livewire::actingAs($organizer)->test(EditionTeamsIndex::class)
            ->set('editionId', (string) $foreignEntry->games_edition_id)
            ->call('edit', $foreignEntry->id));

        $this->assertDatabaseHas('games_edition_teams', ['id' => $foreignEntry->id]);
    }

    public function test_delegate_cannot_mount_the_screens(): void
    {
        $delegate = $this->delegate();

        Livewire::actingAs($delegate)->test(EditionGroupsIndex::class)->assertForbidden();
        Livewire::actingAs($delegate)->test(EditionTeamsIndex::class)->assertForbidden();
    }
}
