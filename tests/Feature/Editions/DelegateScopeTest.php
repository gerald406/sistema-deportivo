<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Models\GamesEditionTeam;
use App\Models\Player;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Policies\SeasonTeamPlayerPolicy;
use App\Policies\SeasonTeamPolicy;
use App\Services\EditionTeamService;
use App\Services\SeasonTeamPlayerService;
use App\Services\TeamRegistrationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * F2 (D4, D9): el delegado titular y el suplente de una delegacion inscrita
 * gestionan sus planteles; nadie mas. Los delegados viven solo en
 * games_edition_teams.
 */
class DelegateScopeTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private function rosterPayload(SeasonTeam $seasonTeam, Player $player): array
    {
        return [
            'season_team_id' => $seasonTeam->id,
            'player_id' => $player->id,
            'shirt_number' => 99,
            'position_id' => null,
            'is_captain' => false,
            'status' => 'active',
            'enrolled_at' => '2026-10-20',
            'observations' => null,
        ];
    }

    public function test_delegate_columns_were_removed(): void
    {
        $this->assertFalse(Schema::hasColumn('teams', 'delegate_id'));
        $this->assertFalse(Schema::hasColumn('season_team', 'delegate_id'));
    }

    public function test_titular_manages_the_roster_of_his_delegation(): void
    {
        $data = $this->buildCompetition();
        $titular = $this->delegate();
        $this->registerDelegation($data->edition, $data->homeTeam, $titular);

        $this->assertTrue(SeasonTeamPlayerPolicy::managesSeasonTeam($titular, $data->home->fresh()));
        $this->assertTrue($titular->can('update', $data->homeEntry->fresh()));

        $entry = app(SeasonTeamPlayerService::class)->register($this->rosterPayload($data->home, Player::factory()->create(['created_by' => $titular->id])), $titular);
        $this->assertSame($data->home->id, $entry->season_team_id);
    }

    public function test_substitute_has_the_same_access(): void
    {
        $data = $this->buildCompetition();
        $substitute = $this->delegate();
        $this->registerDelegation($data->edition, $data->homeTeam, $this->delegate(), $substitute);

        $this->assertTrue(SeasonTeamPlayerPolicy::managesSeasonTeam($substitute, $data->home->fresh()));
        $this->assertTrue($substitute->can('delete', $data->homeEntry->fresh()));
        $this->assertTrue($substitute->can('printCard', $data->homeEntry->fresh()));
        $this->assertTrue($substitute->can('printRoster', $data->home->fresh()));

        $entry = app(SeasonTeamPlayerService::class)->register($this->rosterPayload($data->home, Player::factory()->create(['created_by' => $substitute->id])), $substitute);
        $this->assertNotNull($entry->id);
    }

    public function test_foreign_delegate_is_forbidden(): void
    {
        $data = $this->buildCompetition();
        $homeDelegate = $this->delegate();
        $this->registerDelegation($data->edition, $data->homeTeam, $homeDelegate);

        // Delegado de la delegacion visitante: no toca el plantel local.
        $this->assertFalse(SeasonTeamPlayerPolicy::managesSeasonTeam($homeDelegate, $data->away->fresh()));
        $this->assertFalse($homeDelegate->can('update', $data->awayEntry->fresh()));
        $this->assertFalse($homeDelegate->can('printCard', $data->awayEntry->fresh()));

        $this->expectException(AuthorizationException::class);
        app(SeasonTeamPlayerService::class)->register($this->rosterPayload($data->away, Player::factory()->create()), $homeDelegate);
    }

    public function test_delegate_of_the_same_team_in_another_edition_is_forbidden(): void
    {
        $data = $this->buildCompetition();
        $other = $this->buildCompetition();
        $delegate = $this->delegate();

        // Es delegado de homeTeam, pero en OTRA olimpiada.
        $this->registerDelegation($other->edition, $data->homeTeam, $delegate);

        $this->assertFalse(SeasonTeamPlayerPolicy::managesSeasonTeam($delegate, $data->home->fresh()));
        $this->assertFalse(SeasonTeam::query()->where(SeasonTeamPolicy::delegateConstraint($delegate))->whereKey($data->home->id)->exists());
    }

    public function test_removed_substitute_loses_access(): void
    {
        $data = $this->buildCompetition();
        $titular = $this->delegate();
        $substitute = $this->delegate();
        $entry = $this->registerDelegation($data->edition, $data->homeTeam, $titular, $substitute);

        $this->assertTrue(SeasonTeamPlayerPolicy::managesSeasonTeam($substitute, $data->home->fresh()));

        app(EditionTeamService::class)->update($entry, ['delegate_id' => $titular->id, 'substitute_delegate_id' => null], $data->organizer);

        $this->assertFalse(SeasonTeamPlayerPolicy::managesSeasonTeam($substitute, $data->home->fresh()));
        $this->assertTrue(SeasonTeamPlayerPolicy::managesSeasonTeam($titular, $data->home->fresh()));
    }

    public function test_sql_constraint_matches_the_model_rule(): void
    {
        $data = $this->buildCompetition();
        $titular = $this->delegate();
        $substitute = $this->delegate();
        $stranger = $this->delegate();
        $this->registerDelegation($data->edition, $data->homeTeam, $titular, $substitute);

        foreach ([[$titular, true], [$substitute, true], [$stranger, false]] as [$user, $expected]) {
            $ids = SeasonTeam::query()->where(SeasonTeamPolicy::delegateConstraint($user))->pluck('id')->all();

            $this->assertSame($expected, in_array($data->home->id, $ids, true));
            $this->assertNotContains($data->away->id, $ids);
            $this->assertSame($expected, $data->home->fresh()->hasDelegate($user));
        }
    }

    public function test_delegate_cannot_edit_or_create_delegations(): void
    {
        $data = $this->buildCompetition();
        $delegate = $this->delegate();
        $this->registerDelegation($data->edition, $data->homeTeam, $delegate);

        $this->assertFalse($delegate->can('teams.manage'));
        $this->assertFalse($delegate->can('create', Team::class));
        $this->assertFalse($delegate->can('update', $data->homeTeam));
        $this->assertFalse($delegate->can('delete', $data->homeTeam));
        $this->actingAs($delegate)->get(route('teams.index'))->assertForbidden();

        try {
            app(TeamRegistrationService::class)->update($data->homeTeam, ['name' => 'Renombrada', 'is_active' => true], $delegate);
            $this->fail('El delegado editó su delegación.');
        } catch (AuthorizationException) {
            $this->assertNotSame('Renombrada', $data->homeTeam->fresh()->name);
        }

        $this->expectException(AuthorizationException::class);
        app(TeamRegistrationService::class)->register(['name' => 'Nueva', 'is_active' => true], $delegate);
    }

    public function test_organizer_manages_delegations_registered_in_his_edition(): void
    {
        $data = $this->buildCompetition();
        $foreignTeam = Team::factory()->create();

        $this->assertTrue($data->organizer->can('update', $data->homeTeam));
        $this->assertFalse($data->organizer->can('update', $foreignTeam));
        $this->assertTrue($data->organizer->can('categories.manage'));
    }

    public function test_delegate_sees_only_his_delegation_rosters(): void
    {
        $data = $this->buildCompetition();
        $delegate = $this->delegate();
        $this->registerDelegation($data->edition, $data->homeTeam, $delegate);

        $this->actingAs($delegate)->get(route('rosters.index'))
            ->assertOk()
            ->assertSee($data->homeEntry->player->last_name)
            ->assertDontSee($data->awayEntry->player->last_name);
    }

    public function test_user_with_both_roles_gets_the_union(): void
    {
        $organized = $this->buildCompetition();
        $delegated = $this->buildCompetition();

        // Organiza una olimpiada y es delegado suplente en otra.
        $user = $organized->organizer;
        $user->assignRole('delegado');
        $this->registerDelegation($delegated->edition, $delegated->homeTeam, $this->delegate(), $user);

        $response = $this->actingAs($user)->get(route('rosters.index'))->assertOk();

        // Todo el plantel de la olimpiada que organiza...
        $response->assertSee($organized->homeEntry->player->last_name);
        $response->assertSee($organized->awayEntry->player->last_name);
        // ...mas el de SU delegacion en la otra, pero no el del rival.
        $response->assertSee($delegated->homeEntry->player->last_name);
        $response->assertDontSee($delegated->awayEntry->player->last_name);

        $this->assertTrue(SeasonTeamPlayerPolicy::managesSeasonTeam($user, $delegated->home->fresh()));
        $this->assertFalse(SeasonTeamPlayerPolicy::managesSeasonTeam($user, $delegated->away->fresh()));
    }

    public function test_delegate_without_delegation_sees_nothing(): void
    {
        $data = $this->buildCompetition();
        $delegate = $this->delegate();

        $this->actingAs($delegate)->get(route('rosters.index'))
            ->assertOk()
            ->assertDontSee($data->homeEntry->player->last_name);

        $this->assertSame(0, GamesEditionTeam::where('delegate_id', $delegate->id)->count());
    }
}
