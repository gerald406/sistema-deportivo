<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Enums\EditionTeamStatus;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\GamesEditionTeam;
use App\Models\Team;
use App\Services\EditionTeamService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** F2 (VII.b, Art. 18, D5, D9): delegaciones inscritas con titular y suplente. */
class EditionTeamTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private function assertRejects(string $field, callable $action): void
    {
        try {
            $action();
            $this->fail("Se esperaba un error de validación en {$field}.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_organizer_registers_a_delegation_with_both_delegates(): void
    {
        $organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $group = GamesEditionGroup::create(['games_edition_id' => $edition->id, 'name' => 'Serie A']);
        $team = Team::factory()->create(['created_by' => $organizer->id]);
        $titular = $this->delegate();
        $substitute = $this->delegate();

        $entry = app(EditionTeamService::class)->register($edition, [
            'team_id' => $team->id,
            'group_id' => $group->id,
            'delegate_id' => $titular->id,
            'substitute_delegate_id' => $substitute->id,
        ], $organizer);

        $this->assertSame($group->id, $entry->group_id);
        $this->assertSame($titular->id, $entry->delegate_id);
        $this->assertSame($substitute->id, $entry->substitute_delegate_id);
        $this->assertSame(EditionTeamStatus::Registered, $entry->refresh()->status);
        $this->assertFalse($entry->fee_paid);
        $this->assertTrue($entry->hasDelegate($titular));
        $this->assertTrue($entry->hasDelegate($substitute));
        $this->assertFalse($entry->hasDelegate($organizer));
    }

    public function test_delegation_is_registered_once_per_edition(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $other = GamesEdition::factory()->create();
        $team = Team::factory()->create();
        $service = app(EditionTeamService::class);

        $service->register($edition, ['team_id' => $team->id], $admin);
        $service->register($other, ['team_id' => $team->id], $admin);

        $this->assertRejects('form.team_id', fn () => $service->register($edition, ['team_id' => $team->id], $admin));
        $this->assertSame(2, GamesEditionTeam::where('team_id', $team->id)->count());
    }

    public function test_organizer_only_registers_delegations_in_his_charge(): void
    {
        $organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $foreign = Team::factory()->create(['created_by' => $this->organizer()->id]);
        $inactive = Team::factory()->create(['created_by' => $organizer->id, 'is_active' => false]);
        $service = app(EditionTeamService::class);

        $this->assertRejects('form.team_id', fn () => $service->register($edition, ['team_id' => $foreign->id], $organizer));
        $this->assertRejects('form.team_id', fn () => $service->register($edition, ['team_id' => $inactive->id], $organizer));
        $this->assertRejects('form.team_id', fn () => $service->register($edition, ['team_id' => 999999], $organizer));

        // El admin si puede inscribir cualquier delegacion activa.
        $this->assertNotNull($service->register($edition, ['team_id' => $foreign->id], $this->admin())->id);
    }

    public function test_delegates_must_have_the_delegate_role(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $team = Team::factory()->create();
        $notDelegate = $this->organizer();
        $inactive = $this->delegate(['is_active' => false]);
        $service = app(EditionTeamService::class);

        $this->assertRejects('form.delegate_id', fn () => $service->register($edition, ['team_id' => $team->id, 'delegate_id' => $notDelegate->id], $admin));
        $this->assertRejects('form.delegate_id', fn () => $service->register($edition, ['team_id' => $team->id, 'delegate_id' => $inactive->id], $admin));
        $this->assertRejects('form.substitute_delegate_id', fn () => $service->register($edition, ['team_id' => $team->id, 'substitute_delegate_id' => $notDelegate->id], $admin));
        $this->assertSame(0, GamesEditionTeam::count());
    }

    public function test_titular_and_substitute_must_differ(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $delegate = $this->delegate();

        $this->assertRejects('form.substitute_delegate_id', fn () => app(EditionTeamService::class)->register($edition, [
            'team_id' => Team::factory()->create()->id,
            'delegate_id' => $delegate->id,
            'substitute_delegate_id' => $delegate->id,
        ], $admin));
    }

    public function test_group_must_belong_to_the_same_edition(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $foreignGroup = GamesEditionGroup::create(['games_edition_id' => GamesEdition::factory()->create()->id, 'name' => 'Serie Z']);

        $this->assertRejects('form.group_id', fn () => app(EditionTeamService::class)->register($edition, [
            'team_id' => Team::factory()->create()->id,
            'group_id' => $foreignGroup->id,
        ], $admin));
    }

    public function test_other_organizer_cannot_register_update_or_delete(): void
    {
        $owner = $this->organizer();
        $intruder = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $owner->id]);
        $entry = $this->registerDelegation($edition, Team::factory()->create());
        $service = app(EditionTeamService::class);

        foreach ([
            fn () => $service->register($edition, ['team_id' => Team::factory()->create()->id], $intruder),
            fn () => $service->update($entry, ['status' => 'disqualified'], $intruder),
            fn () => $service->delete($entry, $intruder),
            fn () => $service->register($edition, ['team_id' => Team::factory()->create()->id], $this->delegate()),
        ] as $action) {
            try {
                $action();
                $this->fail('Se esperaba AuthorizationException.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(EditionTeamStatus::Registered, $entry->refresh()->status);
    }

    public function test_delegation_with_rosters_is_not_deleted(): void
    {
        $data = $this->buildCompetition();
        $entry = GamesEditionTeam::where('games_edition_id', $data->edition->id)->where('team_id', $data->homeTeam->id)->firstOrFail();

        $result = app(EditionTeamService::class)->delete($entry, $data->organizer);

        $this->assertIsString($result);
        $this->assertDatabaseHas('games_edition_teams', ['id' => $entry->id]);
    }

    public function test_delegation_without_rosters_is_deleted(): void
    {
        $admin = $this->admin();
        $entry = $this->registerDelegation(GamesEdition::factory()->create(), Team::factory()->create());

        $this->assertTrue(app(EditionTeamService::class)->delete($entry, $admin));
        $this->assertDatabaseMissing('games_edition_teams', ['id' => $entry->id]);
    }

    public function test_delegation_can_be_disqualified_and_marked_as_paid(): void
    {
        $admin = $this->admin();
        $entry = $this->registerDelegation(GamesEdition::factory()->create(), Team::factory()->create());

        app(EditionTeamService::class)->update($entry, ['status' => 'disqualified', 'fee_paid' => true, 'observations' => 'Art. 22'], $admin);

        $entry->refresh();
        $this->assertSame(EditionTeamStatus::Disqualified, $entry->status);
        $this->assertTrue($entry->fee_paid);
        $this->assertSame('Art. 22', $entry->observations);

        $this->assertRejects('form.status', fn () => app(EditionTeamService::class)->update($entry, ['status' => 'inventado'], $admin));
    }

    public function test_delegation_cannot_be_deleted_from_the_catalogue_while_registered(): void
    {
        $admin = $this->admin();
        $team = Team::factory()->create();
        $this->registerDelegation(GamesEdition::factory()->create(), $team);

        $result = app(\App\Services\TeamRegistrationService::class)->delete($team);

        $this->assertIsString($result);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_screen_lists_only_delegations_of_the_managed_edition(): void
    {
        $organizer = $this->organizer();
        $own = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $foreign = GamesEdition::factory()->create();
        $this->registerDelegation($own, Team::factory()->create(['name' => 'IESP Propio']), $this->delegate(['name' => 'Titular Propio']));
        $this->registerDelegation($foreign, Team::factory()->create(['name' => 'IESP Ajeno']));

        $this->actingAs($organizer)->get(route('edition-teams.index'))
            ->assertOk()->assertSee('IESP Propio')->assertSee('Titular Propio')->assertDontSee('IESP Ajeno');

        $this->actingAs($organizer)->get(route('edition-teams.index', ['olimpiada' => $foreign->id]))
            ->assertOk()->assertDontSee('IESP Ajeno');

        $this->actingAs($this->delegate())->get(route('edition-teams.index'))->assertForbidden();
    }
}
