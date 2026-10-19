<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Enums\GenderScope;
use App\Livewire\EditionEvents\Index as EditionEventsIndex;
use App\Models\Category;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\Sport;
use App\Services\EditionEventService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** F3 (D6, D7): cuadro de puntaje por categorias, editable solo por el admin. */
class EditionEventTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    private function row(array $overrides = []): array
    {
        return $overrides + [
            'label' => 'Fútbol Libre',
            'sort_order' => 1,
            'sport_id' => null,
            'category_id' => null,
            'gender_scope' => 'male',
            'separate_podiums' => true,
            'per_discipline' => false,
            'pts_first' => 24,
            'pts_second' => 20,
            'pts_third' => 16,
            'min_players' => 7,
            'max_players' => 18,
            'rules_blocking' => false,
            'is_active' => true,
        ];
    }

    private function assertRejects(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail("Se esperaba un error de validación en {$field}.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_only_admin_creates_updates_and_deletes_rows(): void
    {
        $organizer = $this->organizer();
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $service = app(EditionEventService::class);

        $event = $service->register($edition, $this->row(), $admin);
        $this->assertSame(24, $event->pts_first);
        $this->assertSame(GenderScope::Male, $event->gender_scope);

        $service->update($event, $this->row(['pts_first' => 30]), $admin);
        $this->assertSame(30, $event->refresh()->pts_first);

        foreach ([
            fn () => $service->register($edition, $this->row(['label' => 'Otra']), $organizer),
            fn () => $service->update($event, $this->row(['pts_first' => 99]), $organizer),
            fn () => $service->delete($event, $organizer),
        ] as $forbidden) {
            try {
                $forbidden();
                $this->fail('El organizador pudo modificar el cuadro de puntaje.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(30, $event->refresh()->pts_first);
        $this->assertTrue($service->delete($event, $admin));
        $this->assertDatabaseMissing('games_edition_events', ['id' => $event->id]);
    }

    public function test_organizer_can_view_the_table_but_not_edit(): void
    {
        $organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $event = GamesEditionEvent::factory()->create(['games_edition_id' => $edition->id, 'label' => 'Voleibol Libre', 'pts_first' => 20]);

        $this->actingAs($organizer)->get(route('edition-events.index'))
            ->assertOk()->assertSee('Voleibol Libre')->assertDontSee('Nueva fila')->assertDontSee('fa-pen');

        $this->actingAs($this->admin())->get(route('edition-events.index', ['olimpiada' => $edition->id]))
            ->assertOk()->assertSee('Nueva fila')->assertSee('fa-pen');

        Livewire::actingAs($organizer)->test(EditionEventsIndex::class)->call('create')->assertForbidden();
        Livewire::actingAs($organizer)->test(EditionEventsIndex::class)->call('edit', $event->id)->assertForbidden();
        Livewire::actingAs($organizer)->test(EditionEventsIndex::class)->call('delete', $event->id)->assertForbidden();
        Livewire::actingAs($organizer)->test(EditionEventsIndex::class)
            ->set('editingId', $event->id)
            ->set('form.label', 'Cambiada')
            ->set('form.pts_first', 99)
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseHas('games_edition_events', ['id' => $event->id, 'label' => 'Voleibol Libre', 'pts_first' => 20]);
    }

    public function test_other_organizer_cannot_view(): void
    {
        $edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer()->id]);
        GamesEditionEvent::factory()->create(['games_edition_id' => $edition->id, 'label' => 'Fila Ajena']);
        $other = $this->organizer();

        $this->actingAs($other)->get(route('edition-events.index'))->assertOk()->assertDontSee('Fila Ajena');
        $this->actingAs($other)->get(route('edition-events.index', ['olimpiada' => $edition->id]))->assertOk()->assertDontSee('Fila Ajena');
        $this->actingAs($this->delegate())->get(route('edition-events.index'))->assertForbidden();
    }

    public function test_admin_edits_a_row_from_the_screen(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $event = GamesEditionEvent::factory()->create(['games_edition_id' => $edition->id, 'label' => 'Danza']);

        Livewire::actingAs($admin)->test(EditionEventsIndex::class)
            ->set('editionId', (string) $edition->id)
            ->call('edit', $event->id)
            ->assertSet('form.label', 'Danza')
            ->set('form.pts_first', 12)
            ->set('form.pts_second', 8)
            ->set('form.pts_third', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('games_edition_events', ['id' => $event->id, 'pts_first' => 12, 'pts_second' => 8, 'pts_third' => null]);
    }

    public function test_row_of_another_edition_is_out_of_reach_from_the_screen(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $foreign = GamesEditionEvent::factory()->create();

        try {
            Livewire::actingAs($admin)->test(EditionEventsIndex::class)
                ->set('editionId', (string) $edition->id)
                ->call('delete', $foreign->id)
                ->assertNotFound();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('games_edition_events', ['id' => $foreign->id]);
    }

    public function test_screen_filters_rows_and_their_sum(): void
    {
        $organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $futsal = Sport::create(['name' => 'Futsal', 'slug' => 'futsal', 'format_type' => 'head_to_head', 'is_active' => true]);
        $libre = Category::create(['name' => 'Libre', 'min_age' => 25, 'gender' => 'X', 'is_active' => true]);
        $row = fn (array $data) => GamesEditionEvent::factory()->create(['games_edition_id' => $edition->id] + $data);

        $row(['label' => 'Futsal Libre', 'sport_id' => $futsal->id, 'category_id' => $libre->id, 'gender_scope' => GenderScope::Male, 'pts_first' => 24]);
        $row(['label' => 'Futsal Máster', 'sport_id' => $futsal->id, 'gender_scope' => GenderScope::Female, 'pts_first' => 20, 'is_active' => false]);
        $row(['label' => 'Estudiantina', 'pts_first' => 10]);
        GamesEditionEvent::factory()->create(['label' => 'Futsal Ajeno', 'sport_id' => $futsal->id]);

        $labels = fn ($component) => $component->viewData('events')->pluck('label')->all();
        $screen = Livewire::actingAs($organizer)->test(EditionEventsIndex::class)->set('editionId', (string) $edition->id);

        $this->assertSame(['Estudiantina', 'Futsal Libre', 'Futsal Máster'], $labels($screen));
        $this->assertSame(['Futsal Libre', 'Futsal Máster'], $labels($screen->set('search', 'futsal')));
        $this->assertSame(['Futsal Libre'], $labels($screen->set('statusFilter', 'active')->assertSee('Suma de las filas activas filtradas')));
        $this->assertSame([], $labels($screen->set('scopeFilter', 'female')->assertSee('Ninguna fila coincide con los filtros.')));

        $screen->call('clearFilters')->assertSet('statusFilter', 'all');
        $this->assertSame(['Estudiantina'], $labels($screen->set('sportFilter', 'none')));
        $this->assertSame(['Futsal Libre'], $labels($screen->set('sportFilter', (string) $futsal->id)->set('categoryFilter', (string) $libre->id)));
        $this->assertSame(['Futsal Máster'], $labels($screen->set('categoryFilter', 'none')));

        // Un valor manipulado desde el navegador no filtra ni revienta.
        $this->assertCount(3, $labels($screen->call('clearFilters')->set('scopeFilter', 'x')->set('sportFilter', '1 OR 1=1')));
    }

    public function test_points_are_optional(): void
    {
        $edition = GamesEdition::factory()->create();

        $event = app(EditionEventService::class)->register($edition, $this->row([
            'label' => 'Danza', 'gender_scope' => 'none',
            'pts_first' => '', 'pts_second' => null, 'pts_third' => '',
            'min_players' => '', 'max_players' => '',
        ]), $this->admin());

        $this->assertNull($event->pts_first);
        $this->assertNull($event->min_players);
        $this->assertSame(0, $event->pointsFor(1));
        $this->assertSame(0, $event->pointsFor(2));
        $this->assertSame(0, $event->pointsFor(3));
        $this->assertSame(0, $event->pointsFor(4));
    }

    public function test_points_must_be_non_negative_and_descending_when_present(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $service = app(EditionEventService::class);

        $this->assertRejects(fn () => $service->register($edition, $this->row(['pts_first' => 10, 'pts_second' => 12]), $admin), 'form.pts_second');
        $this->assertRejects(fn () => $service->register($edition, $this->row(['pts_second' => 5, 'pts_third' => 6]), $admin), 'form.pts_third');
        $this->assertRejects(fn () => $service->register($edition, $this->row(['pts_second' => '', 'pts_first' => 5, 'pts_third' => 6]), $admin), 'form.pts_third');
        $this->assertRejects(fn () => $service->register($edition, $this->row(['pts_third' => -1]), $admin), 'form.pts_third');

        $this->assertSame(0, GamesEditionEvent::count());

        // Puntos iguales entre puestos son validos (la comision decide).
        $event = $service->register($edition, $this->row(['pts_first' => 5, 'pts_second' => 5, 'pts_third' => 0]), $admin);
        $this->assertSame(5, $event->pointsFor(2));
    }

    public function test_label_is_unique_per_edition(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $service = app(EditionEventService::class);

        $first = $service->register($edition, $this->row(), $admin);
        $service->register(GamesEdition::factory()->create(), $this->row(), $admin);

        $this->assertRejects(fn () => $service->register($edition, $this->row(['label' => ' Fútbol Libre ']), $admin), 'form.label');

        // Guardar la misma fila con su propia etiqueta es valido.
        $service->update($first, $this->row(['pts_first' => 25]), $admin);
        $this->assertSame(25, $first->refresh()->pts_first);
    }

    public function test_min_players_cannot_exceed_max_players(): void
    {
        $admin = $this->admin();
        $edition = GamesEdition::factory()->create();
        $service = app(EditionEventService::class);

        $this->assertRejects(fn () => $service->register($edition, $this->row(['min_players' => 12, 'max_players' => 10]), $admin), 'form.max_players');

        $event = $service->register($edition, $this->row(['min_players' => 4, 'max_players' => 4]), $admin);
        $this->assertSame(4, $event->max_players);
    }

    public function test_genders_resolution(): void
    {
        $make = fn (string $scope, bool $separate) => GamesEditionEvent::factory()->make(['gender_scope' => $scope, 'separate_podiums' => $separate]);

        $this->assertSame(['M'], $make('male', true)->genders());
        $this->assertSame(['F'], $make('female', true)->genders());
        $this->assertSame(['M', 'F'], $make('both', true)->genders());
        $this->assertSame(['X'], $make('both', false)->genders());
        $this->assertSame([null], $make('none', true)->genders());
    }

    public function test_row_with_competitions_is_not_deleted(): void
    {
        // La parte "con competencias devuelve un string" se completa en F4
        // (CompetitionGeneratorTest), cuando seasons.games_edition_event_id exista.
        $admin = $this->admin();
        $event = GamesEditionEvent::factory()->create();

        $this->assertTrue(app(EditionEventService::class)->delete($event, $admin));
        $this->assertDatabaseMissing('games_edition_events', ['id' => $event->id]);
    }
}
