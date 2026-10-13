<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\GamesEditionTeam;
use App\Models\Team;
use App\Services\EditionGroupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** F2 (Art. 8 de las bases): series de la olimpiada. */
class EditionGroupTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    public function test_organizer_creates_groups_in_own_edition(): void
    {
        $organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);

        $group = app(EditionGroupService::class)->register($edition, ['name' => ' Serie A ', 'sort_order' => 1], $organizer);

        $this->assertSame('Serie A', $group->name);
        $this->assertSame(1, $group->sort_order);
        $this->assertSame($edition->id, $group->games_edition_id);
    }

    public function test_other_organizer_cannot_create_groups(): void
    {
        $edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer()->id]);

        $this->expectException(AuthorizationException::class);
        app(EditionGroupService::class)->register($edition, ['name' => 'Serie A', 'sort_order' => 1], $this->organizer());
    }

    public function test_delegate_cannot_create_groups(): void
    {
        $edition = GamesEdition::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(EditionGroupService::class)->register($edition, ['name' => 'Serie A', 'sort_order' => 1], $this->delegate());
    }

    public function test_group_name_is_unique_per_edition(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $other = GamesEdition::factory()->create();
        $service = app(EditionGroupService::class);

        $service->register($edition, ['name' => 'Serie A', 'sort_order' => 1], $admin);
        $service->register($other, ['name' => 'Serie A', 'sort_order' => 1], $admin);

        try {
            $service->register($edition, ['name' => 'Serie A', 'sort_order' => 2], $admin);
            $this->fail('Se aceptó una serie repetida en la misma olimpiada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('form.name', $e->errors());
        }

        $this->assertSame(2, GamesEditionGroup::where('name', 'Serie A')->count());
    }

    public function test_renaming_to_an_existing_name_is_rejected(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $service = app(EditionGroupService::class);

        $service->register($edition, ['name' => 'Serie A', 'sort_order' => 1], $admin);
        $b = $service->register($edition, ['name' => 'Serie B', 'sort_order' => 2], $admin);

        // Guardar la misma serie con su propio nombre es valido.
        $service->update($b, ['name' => 'Serie B', 'sort_order' => 5], $admin);
        $this->assertSame(5, $b->refresh()->sort_order);

        $this->expectException(ValidationException::class);
        $service->update($b, ['name' => 'Serie A', 'sort_order' => 2], $admin);
    }

    public function test_group_with_delegations_is_not_deleted(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $group = app(EditionGroupService::class)->register($edition, ['name' => 'Serie A', 'sort_order' => 1], $admin);
        GamesEditionTeam::create(['games_edition_id' => $edition->id, 'team_id' => Team::factory()->create()->id, 'group_id' => $group->id]);

        $result = app(EditionGroupService::class)->delete($group, $admin);

        $this->assertIsString($result);
        $this->assertDatabaseHas('games_edition_groups', ['id' => $group->id]);
    }

    public function test_empty_group_is_deleted(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $group = app(EditionGroupService::class)->register($edition, ['name' => 'Serie A', 'sort_order' => 1], $admin);

        $this->assertTrue(app(EditionGroupService::class)->delete($group, $admin));
        $this->assertDatabaseMissing('games_edition_groups', ['id' => $group->id]);
    }

    public function test_screen_lists_only_groups_of_the_managed_edition(): void
    {
        $organizer = $this->organizer();
        $own = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $foreign = GamesEdition::factory()->create();
        GamesEditionGroup::create(['games_edition_id' => $own->id, 'name' => 'Serie Propia']);
        GamesEditionGroup::create(['games_edition_id' => $foreign->id, 'name' => 'Serie Ajena']);

        $this->actingAs($organizer)->get(route('edition-groups.index'))
            ->assertOk()->assertSee('Serie Propia')->assertDontSee('Serie Ajena');

        // Forzar la olimpiada ajena por la URL no la muestra.
        $this->actingAs($organizer)->get(route('edition-groups.index', ['olimpiada' => $foreign->id]))
            ->assertOk()->assertDontSee('Serie Ajena');

        $this->actingAs($this->delegate())->get(route('edition-groups.index'))->assertForbidden();
    }
}
