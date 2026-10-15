<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Livewire\GamesEditions\Index as GamesEditionsIndex;
use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\Sport;
use App\Services\GamesEditionService;
use App\Services\SeasonService;
use App\Services\UserAccountService;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * F1 (D1, D2): solo el admin crea la olimpiada y asigna organizador; el
 * organizador asignado la gestiona y nadie mas.
 */
class GamesEditionAuthorizationTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'XXVIII Juegos Deportivos Regionales 2026',
            'organizer_id' => null,
            'start_date' => '2026-10-28',
            'end_date' => '2026-10-30',
            'is_active' => true,
        ];
    }

    private function phases(): array
    {
        return [['id' => null, 'phase_name' => 'Fase única', 'pts_win' => 3, 'pts_draw' => 1, 'pts_loss' => 0, 'pts_walkover' => -1]];
    }

    public function test_only_admin_creates_an_edition(): void
    {
        $admin = $this->admin();
        $organizer = $this->organizer();
        $service = app(GamesEditionService::class);

        $edition = $service->register($this->payload(['organizer_id' => $organizer->id]), $admin);
        $this->assertSame($organizer->id, $edition->organizer_id);

        $this->expectException(AuthorizationException::class);
        $service->register($this->payload(['name' => 'Otra', 'organizer_id' => $organizer->id]), $organizer);
    }

    public function test_organizer_must_be_an_active_organizer_or_admin(): void
    {
        $admin = $this->admin();
        $delegate = $this->delegate();
        $inactive = $this->organizer(['is_active' => false]);
        $service = app(GamesEditionService::class);

        foreach ([$delegate->id, $inactive->id, 0] as $organizerId) {
            try {
                $service->register($this->payload(['organizer_id' => $organizerId]), $admin);
                $this->fail("Se aceptó un organizador inválido ({$organizerId}).");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('form.organizer_id', $e->errors());
            }
        }

        $this->assertSame(0, GamesEdition::count());
    }

    public function test_assigned_organizer_updates_but_cannot_reassign(): void
    {
        $organizer = $this->organizer();
        $other = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);

        app(GamesEditionService::class)->update(
            $edition,
            $this->payload(['name' => 'Nombre nuevo', 'organizer_id' => $other->id]),
            $organizer,
        );

        $edition->refresh();
        $this->assertSame('Nombre nuevo', $edition->name);
        $this->assertSame($organizer->id, $edition->organizer_id);
    }

    public function test_admin_can_reassign_the_organizer(): void
    {
        $admin = $this->admin();
        $other = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer()->id]);

        app(GamesEditionService::class)->update($edition, $this->payload(['organizer_id' => $other->id]), $admin);

        $this->assertSame($other->id, $edition->refresh()->organizer_id);
    }

    public function test_other_organizer_is_forbidden(): void
    {
        $owner = $this->organizer();
        $intruder = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $owner->id]);
        $season = Season::factory()->create(['games_edition_id' => $edition->id]);

        $this->assertTrue($owner->can('update', $edition));
        $this->assertTrue($owner->can('manage', $edition));
        $this->assertTrue(SeasonAccess::manages($owner, $season));
        $this->assertTrue($owner->can('update', $season));

        $this->assertFalse($intruder->can('update', $edition));
        $this->assertFalse($intruder->can('manage', $edition));
        $this->assertFalse(SeasonAccess::manages($intruder, $season));
        $this->assertFalse($intruder->can('update', $season));
        $this->assertFalse($intruder->can('delete', $season));

        $this->expectException(AuthorizationException::class);
        app(GamesEditionService::class)->update($edition, $this->payload(['name' => 'Tomada']), $intruder);
    }

    public function test_delegate_does_not_manage_editions(): void
    {
        $delegate = $this->delegate();
        $edition = GamesEdition::factory()->create();

        $this->assertFalse($delegate->can('viewAny', GamesEdition::class));
        $this->assertFalse($delegate->can('manage', $edition));
        $this->actingAs($delegate)->get(route('games-editions.index'))->assertForbidden();
    }

    public function test_only_admin_deletes_an_edition(): void
    {
        $organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);

        $this->assertFalse($organizer->can('delete', $edition));
        $this->assertFalse($organizer->can('create', GamesEdition::class));
        $this->assertTrue($this->admin()->can('delete', $edition));
    }

    public function test_only_own_edition_competitions_are_edited(): void
    {
        $organizer = $this->organizer();
        $foreign = Season::factory()->create(['games_edition_id' => GamesEdition::factory()->create()->id, 'name' => 'Ajena']);

        $data = ['name' => 'Tomada', 'start_date' => null, 'end_date' => null, 'status' => 'draft', 'is_active' => true];

        try {
            app(SeasonService::class)->update($foreign, $data, $this->phases(), $organizer);
            $this->fail('Se editó una competencia de una olimpiada ajena.');
        } catch (AuthorizationException) {
            $this->assertSame('Ajena', $foreign->refresh()->name);
        }
    }

    public function test_competition_keeps_what_comes_from_the_scoring_table(): void
    {
        $organizer = $this->organizer();
        $own = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $foreign = GamesEdition::factory()->create();
        $season = Season::factory()->create(['games_edition_id' => $own->id, 'gender' => 'M']);
        $sportId = $season->sport_id;

        // Olimpiada, deporte y genero llegan del navegador: se ignoran.
        app(SeasonService::class)->update($season, [
            'games_edition_id' => $foreign->id, 'sport_id' => Sport::factory()->create()->id, 'gender' => 'F',
            'name' => 'Nombre nuevo', 'start_date' => '2026-10-28', 'end_date' => '2026-10-29', 'status' => 'active', 'is_active' => true,
        ], $this->phases(), $organizer);

        $season->refresh();
        $this->assertSame([$own->id, $sportId, 'M'], [$season->games_edition_id, $season->sport_id, $season->gender->value]);
        $this->assertSame(['Nombre nuevo', '2026-10-29'], [$season->name, $season->end_date->toDateString()]);
    }

    public function test_user_organizing_an_edition_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $organizer = $this->organizer();
        GamesEdition::factory()->create(['organizer_id' => $organizer->id]);

        $result = app(UserAccountService::class)->delete($organizer, $admin);

        $this->assertIsString($result);
        $this->assertDatabaseHas('users', ['id' => $organizer->id]);
    }

    public function test_form_requires_both_dates_in_order(): void
    {
        $organizer = $this->organizer();

        $form = fn () => Livewire::actingAs($this->admin())->test(GamesEditionsIndex::class)
            ->call('create')
            ->set('form.name', 'Olimpiada con fechas')
            ->set('form.organizer_id', $organizer->id);

        $form()->set('form.start_date', '')->set('form.end_date', '')
            ->call('save')
            ->assertHasErrors(['form.start_date' => 'required', 'form.end_date' => 'required']);

        $form()->set('form.start_date', '2026-10-30')->set('form.end_date', '2026-10-28')
            ->call('save')
            ->assertHasErrors(['form.end_date' => 'after_or_equal']);

        $this->assertSame(0, GamesEdition::count());

        $form()->set('form.start_date', '2026-10-28')->set('form.end_date', '2026-10-30')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('2026-10-30', GamesEdition::sole()->end_date->toDateString());
    }

    public function test_registration_deadline_is_optional_and_not_after_the_end(): void
    {
        $edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer()->id, 'end_date' => '2026-10-30']);
        $form = fn () => Livewire::actingAs($this->admin())->test(GamesEditionsIndex::class)->call('edit', $edition->id);

        $form()->set('form.registration_deadline', '2026-10-31')->call('save')->assertHasErrors('form.registration_deadline');

        $form()->set('form.registration_deadline', '2026-10-23')->call('save')->assertHasNoErrors();
        $this->assertSame('2026-10-23', $edition->refresh()->registration_deadline->toDateString());

        $form()->set('form.registration_deadline', '')->call('save')->assertHasNoErrors();
        $this->assertNull($edition->refresh()->registration_deadline);
    }

    public function test_organizer_sees_only_own_editions(): void
    {
        $organizer = $this->organizer();
        GamesEdition::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Olimpiada Propia']);
        GamesEdition::factory()->create(['name' => 'Olimpiada Ajena']);

        $this->actingAs($organizer)->get(route('games-editions.index'))
            ->assertOk()
            ->assertSee('Olimpiada Propia')
            ->assertDontSee('Olimpiada Ajena')
            ->assertDontSee('wire:click="create"', false);

        $this->actingAs($this->admin())->get(route('games-editions.index'))
            ->assertOk()
            ->assertSee('Olimpiada Propia')
            ->assertSee('Olimpiada Ajena')
            ->assertSee('wire:click="create"', false);
    }
}
