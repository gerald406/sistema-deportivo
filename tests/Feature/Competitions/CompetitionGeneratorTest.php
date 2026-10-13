<?php

declare(strict_types=1);

namespace Tests\Feature\Competitions;

use App\Enums\SeasonStatus;
use App\Livewire\Seasons\Index as SeasonsIndex;
use App\Models\Category;
use App\Models\Discipline;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\ScoringConfig;
use App\Models\Season;
use App\Models\Sport;
use App\Services\CompetitionGeneratorService;
use App\Services\EditionEventService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Puno2026Seeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** F4: las competencias salen del cuadro de puntaje (S1, S2; foco 7 del plan maestro). */
class CompetitionGeneratorTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    private GamesEdition $edition;

    private Category $libre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer()->id]);
        $this->libre = Category::create(['name' => 'Libre', 'min_age' => 25, 'gender' => 'X', 'is_active' => true]);
    }

    private function row(string $sportName, string $scope, array $overrides = []): GamesEditionEvent
    {
        $sport = Sport::firstOrCreate(['slug' => str($sportName)->slug()->value()], ['name' => $sportName, 'format_type' => 'head_to_head', 'is_active' => true]);

        return GamesEditionEvent::factory()->create($overrides + [
            'games_edition_id' => $this->edition->id,
            'label' => "{$sportName} Libre {$scope}",
            'sport_id' => $sport->id,
            'category_id' => $this->libre->id,
            'gender_scope' => $scope,
        ]);
    }

    private function generate(): array
    {
        return app(CompetitionGeneratorService::class)->generate($this->edition, $this->edition->organizer);
    }

    public function test_row_for_both_genders_creates_two_competitions(): void
    {
        $row = $this->row('Voleibol', 'both');

        $this->assertSame(['created' => 2, 'existing' => 0, 'skipped' => 0], $this->generate());

        $seasons = Season::where('games_edition_event_id', $row->id)->orderBy('name')->get();
        $this->assertSame(['Voleibol Libre Damas', 'Voleibol Libre Varones'], $seasons->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['F', 'M'], $seasons->map(fn ($s) => $s->gender->value)->all());

        foreach ($seasons as $season) {
            $this->assertSame($this->libre->id, $season->category_id);
            $this->assertSame($row->sport_id, $season->sport_id);
            $this->assertSame(SeasonStatus::Active, $season->status);
            $this->assertTrue($season->is_active);
            $this->assertSame('2026-10-28', $season->start_date->toDateString());
            $this->assertSame('2026-10-30', $season->end_date->toDateString());
            $this->assertSame([CompetitionGeneratorService::DEFAULT_PHASE], $season->scoringConfigs()->pluck('phase_name')->all());
        }
    }

    public function test_row_for_one_gender_creates_one(): void
    {
        $this->row('Fútbol', 'male');

        $this->assertSame(1, $this->generate()['created']);

        $season = Season::sole();
        $this->assertSame('M', $season->gender->value);
        $this->assertSame('Fútbol Libre Varones', $season->name);
    }

    public function test_combined_podium_creates_a_mixed_competition(): void
    {
        $this->row('Ajedrez', 'both', ['separate_podiums' => false]);

        $this->assertSame(1, $this->generate()['created']);
        $this->assertSame('X', Season::sole()->gender->value);
        $this->assertSame('Ajedrez Libre Mixto', Season::sole()->name);
    }

    public function test_row_without_gender_or_level_gets_a_plain_name(): void
    {
        $this->row('Danza', 'none', ['category_id' => null]);

        $this->generate();

        $this->assertSame('Danza', Season::sole()->name);
        $this->assertNull(Season::sole()->gender);
        $this->assertNull(Season::sole()->category_id);
    }

    public function test_per_discipline_row_creates_one_per_discipline(): void
    {
        $row = $this->row('Atletismo', 'both', ['per_discipline' => true]);
        $names = ['100 metros', '400 metros', '1500 metros', '3000 metros', 'Salto largo', 'Lanzamiento de peso', 'Lanzamiento de disco', 'Posta 4x100'];
        foreach ($names as $name) {
            Discipline::create(['sport_id' => $row->sport_id, 'name' => $name, 'unit_of_measure' => 'time', 'better_direction' => 'asc', 'is_active' => true]);
        }
        Discipline::create(['sport_id' => $row->sport_id, 'name' => 'Inactiva', 'unit_of_measure' => 'time', 'better_direction' => 'asc', 'is_active' => false]);

        $this->assertSame(16, $this->generate()['created']);

        $this->assertSame(16, Season::whereNotNull('discipline_id')->count());
        $this->assertSame(8, Season::distinct()->count('discipline_id'));
        $this->assertDatabaseHas('seasons', ['name' => 'Atletismo — 100 metros Libre Damas']);
        $this->assertDatabaseHas('seasons', ['name' => 'Atletismo — Posta 4x100 Libre Varones']);
        $this->assertDatabaseMissing('seasons', ['name' => 'Atletismo — Inactiva Libre Damas']);
    }

    public function test_generation_is_idempotent(): void
    {
        $this->row('Voleibol', 'both');
        $this->row('Fútbol', 'male');

        $this->assertSame(3, $this->generate()['created']);
        $this->assertSame(['created' => 0, 'existing' => 3, 'skipped' => 0], $this->generate());

        $this->assertSame(3, Season::count());
        $this->assertSame(3, ScoringConfig::count());
    }

    public function test_new_row_only_adds_its_competitions(): void
    {
        $this->row('Voleibol', 'both');
        $this->generate();

        $this->row('Futsal', 'both');

        $this->assertSame(['created' => 2, 'existing' => 2, 'skipped' => 0], $this->generate());
        $this->assertSame(4, Season::count());
    }

    public function test_deactivated_competition_is_not_recreated_or_reactivated(): void
    {
        $this->row('Voleibol', 'both');
        $this->generate();

        $off = Season::where('gender', 'F')->sole();
        $off->update(['is_active' => false, 'name' => 'Vóley damas (no se juega)', 'status' => 'closed']);

        $this->assertSame(0, $this->generate()['created']);

        $off->refresh();
        $this->assertFalse($off->is_active);
        $this->assertSame('Vóley damas (no se juega)', $off->name);
        $this->assertSame(SeasonStatus::Closed, $off->status);
        $this->assertSame(2, Season::count());
    }

    public function test_row_without_sport_is_skipped(): void
    {
        GamesEditionEvent::factory()->create(['games_edition_id' => $this->edition->id, 'label' => 'Sin deporte']);

        $this->assertSame(['created' => 0, 'existing' => 0, 'skipped' => 1], $this->generate());
        $this->assertSame(0, Season::count());
    }

    public function test_inactive_row_is_ignored(): void
    {
        $this->row('Voleibol', 'both', ['is_active' => false]);

        $this->assertSame(['created' => 0, 'existing' => 0, 'skipped' => 0], $this->generate());
    }

    public function test_name_taken_by_a_manual_competition_does_not_break_generation(): void
    {
        $row = $this->row('Fútbol', 'male');
        Season::factory()->create(['games_edition_id' => $this->edition->id, 'sport_id' => $row->sport_id, 'name' => 'Fútbol Libre Varones']);

        $this->assertSame(1, $this->generate()['created']);
        $this->assertDatabaseHas('seasons', ['name' => 'Fútbol Libre Varones (2)', 'games_edition_event_id' => $row->id]);
    }

    public function test_only_edition_manager_generates(): void
    {
        $this->row('Fútbol', 'male');
        $service = app(CompetitionGeneratorService::class);

        foreach ([$this->organizer(), $this->delegate()] as $stranger) {
            try {
                $service->generate($this->edition, $stranger);
                $this->fail('Un usuario ajeno generó competencias.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, Season::count());
        $this->assertSame(1, $service->generate($this->edition, $this->admin())['created']);
    }

    public function test_puno_table_generates_expected_total(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(Puno2026Seeder::class);
        $edition = GamesEdition::where('name', Puno2026Seeder::EDITION_NAME)->firstOrFail();

        // El total se calcula desde las filas, no se escribe a mano.
        $expected = $edition->events->sum(function (GamesEditionEvent $row) {
            $disciplines = $row->per_discipline ? Discipline::where('sport_id', $row->sport_id)->where('is_active', true)->count() : 1;

            return count($row->genders()) * $disciplines;
        });

        $result = app(CompetitionGeneratorService::class)->generate($edition, $this->admin());

        $this->assertSame($expected, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame($expected, $edition->seasons()->count());
        $this->assertSame($expected, $edition->seasons()->distinct()->count('name'));

        foreach (['Fútbol Libre Varones', 'Futsal Súper Máster Varones', 'Voleibol Máster Damas', 'Ciclismo Libre Damas',
            'Atletismo — 100 metros Máster Damas', 'Danza Libre', 'Puntualidad y presentación'] as $name) {
            $this->assertDatabaseHas('seasons', ['games_edition_id' => $edition->id, 'name' => $name]);
        }
    }

    public function test_row_with_competitions_is_not_deleted(): void
    {
        $row = $this->row('Fútbol', 'male');
        $this->generate();

        $result = app(EditionEventService::class)->delete($row, $this->admin());

        $this->assertIsString($result);
        $this->assertDatabaseHas('games_edition_events', ['id' => $row->id]);
    }

    public function test_organizer_generates_from_the_screen(): void
    {
        $this->row('Voleibol', 'both');

        Livewire::actingAs($this->edition->organizer)->test(SeasonsIndex::class)
            ->assertSet('editionId', (string) $this->edition->id)
            ->call('generate')
            ->assertDispatched('toast');

        $this->assertSame(2, Season::count());

        $this->actingAs($this->edition->organizer)->get(route('seasons.index'))
            ->assertOk()->assertSee('Voleibol Libre Damas')->assertSee('Generar competencias');
    }

    public function test_screen_lists_only_competitions_of_managed_editions(): void
    {
        $foreign = Season::factory()->create(['name' => 'Competencia Ajena']);
        Season::factory()->create(['games_edition_id' => $this->edition->id, 'name' => 'Competencia Propia']);
        $organizer = $this->edition->organizer;

        $this->actingAs($organizer)->get(route('seasons.index'))->assertOk()->assertSee('Competencia Propia')->assertDontSee('Competencia Ajena');
        $this->actingAs($organizer)->get(route('seasons.index', ['olimpiada' => $foreign->games_edition_id]))->assertOk()->assertDontSee('Competencia Ajena');

        // Un organizador ajeno no genera ni borra desde la pantalla.
        $stranger = $this->organizer();
        Livewire::actingAs($stranger)->test(SeasonsIndex::class)
            ->set('editionId', (string) $this->edition->id)
            ->call('generate')
            ->assertNotFound();
    }
}
