<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Enums\SportFormatType;
use App\Models\Category;
use App\Models\Discipline;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\GamesEditionGroup;
use App\Models\GamesEditionTeam;
use App\Models\Sport;
use App\Models\Team;
use App\Models\Venue;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Puno2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F3: catalogo y datos iniciales de las bases de Puno 2026 (especificacion §5). */
class Puno2026SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(Puno2026Seeder::class);
    }

    private function edition(): GamesEdition
    {
        return GamesEdition::where('name', Puno2026Seeder::EDITION_NAME)->firstOrFail();
    }

    /** @return array{0: ?int, 1: ?int, 2: ?int} */
    private function points(int $number): array
    {
        $row = $this->edition()->events()->where('sort_order', $number)->firstOrFail();

        return [$row->pts_first, $row->pts_second, $row->pts_third];
    }

    public function test_levels_match_the_rules(): void
    {
        $levels = Category::where('is_active', true)->orderBy('min_age')->get();

        $this->assertSame(['Libre', 'Máster', 'Súper Máster'], $levels->pluck('name')->all());
        $this->assertSame([25, 40, 55], $levels->pluck('min_age')->all());
        $this->assertSame([null, null, null], $levels->pluck('max_age')->all());
    }

    public function test_legacy_and_duplicated_levels_are_deactivated_not_deleted(): void
    {
        Category::create(['name' => 'Sub-17', 'min_age' => 15, 'max_age' => 17, 'gender' => 'X', 'is_active' => true]);
        Category::create(['name' => 'Libre', 'min_age' => 25, 'gender' => 'F', 'is_active' => true]);

        $this->seed(\Database\Seeders\CategorySeeder::class);

        $this->assertSame(3, Category::where('is_active', true)->count());
        $this->assertSame(5, Category::count());
    }

    public function test_sports_catalogue_is_complete(): void
    {
        $slugs = ['futbol', 'futsal', 'basquetbol', 'voley', 'ajedrez', 'tenis-de-mesa', 'tiro-al-sapo', 'ciclismo', 'atletismo', 'puntualidad', 'estudiantina', 'danza'];

        $this->assertEqualsCanonicalizing($slugs, Sport::where('is_active', true)->pluck('slug')->all());

        $athletics = Discipline::where('sport_id', Sport::where('slug', 'atletismo')->value('id'))->where('is_active', true)->get();
        $this->assertEqualsCanonicalizing(
            ['100 metros', '400 metros', '1500 metros', '3000 metros', 'Salto largo', 'Lanzamiento de peso', 'Lanzamiento de disco', 'Posta 4x100'],
            $athletics->pluck('name')->all(),
        );
        $this->assertSame(SportFormatType::TeamRelay, $athletics->firstWhere('name', 'Posta 4x100')->format_type);
        $this->assertNull($athletics->firstWhere('name', '100 metros')->format_type);

        foreach (['futbol', 'futsal'] as $slug) {
            $this->assertEqualsCanonicalizing(
                ['goal', 'yellow_card', 'red_card', 'red_card_indirect'],
                Sport::where('slug', $slug)->firstOrFail()->eventTypes()->pluck('code')->all(),
            );
        }
        $this->assertEqualsCanonicalizing(
            ['points', 'foul', 'disqualification'],
            Sport::where('slug', 'basquetbol')->firstOrFail()->eventTypes()->pluck('code')->all(),
        );
    }

    public function test_edition_groups_and_delegations(): void
    {
        $this->assertSame(1, GamesEdition::count());
        $edition = $this->edition();

        $this->assertSame('2026-10-28', $edition->start_date->toDateString());
        $this->assertSame('2026-10-30', $edition->end_date->toDateString());
        $this->assertNotNull($edition->organizer_id);
        $this->assertSame([2, 2], [$edition->yellow_cards_limit, $edition->captain_red_matches]); // Art. 15 y 14

        $perGroup = $edition->groups()->withCount('editionTeams')->get()->pluck('edition_teams_count', 'name')->all();
        $this->assertSame(['Serie A' => 5, 'Serie B' => 4, 'Serie C' => 5, 'Serie D' => 4], $perGroup);

        $this->assertSame(18, $edition->editionTeams()->count());
        $this->assertSame(18, Team::count());
        $this->assertSame(0, $edition->editionTeams()->whereNull('group_id')->count());

        $serieB = GamesEditionGroup::where('name', 'Serie B')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['MNB Juliaca', 'Pedro Vilcapaza', 'ESFA Juliaca', 'Yunguyo Capachica'],
            $serieB->editionTeams()->with('team')->get()->pluck('team.name')->all(),
        );
    }

    public function test_scoring_table_matches_page_9(): void
    {
        $this->assertSame(26, $this->edition()->events()->count());
        $this->assertSame(range(1, 26), $this->edition()->events()->pluck('sort_order')->all());

        // Cuadro de la pag. 9, literal (especificacion §5.5).
        $expected = [
            1 => [18, 14, 10], 2 => [8, 6, 4], 3 => [8, 6, 4], 4 => [8, 6, 4],
            5 => [6, 4, 2], 6 => [6, 4, 2], 7 => [6, 4, 2],
            8 => [20, 16, 12], 9 => [20, 16, 12], 10 => [20, 16, 12],
            11 => [24, 20, 16], 12 => [20, 16, 12], 13 => [20, 16, 12], 14 => [28, 24, 20],
            15 => [10, 8, 6], 16 => [8, 6, 4], 17 => [10, 8, 6],
            18 => [6, 4, 2], 19 => [8, 6, 4], 20 => [24, 20, 16],
            21 => [20, 16, 12], 22 => [24, 20, 16],
            23 => [null, null, null], 24 => [null, null, null], 25 => [null, null, null], 26 => [null, null, null],
        ];

        foreach ($expected as $number => $points) {
            $this->assertSame($points, $this->points($number), "Fila {$number} del cuadro.");
        }

        $this->assertSame(0, $this->edition()->events()->whereNull('sport_id')->count());

        $row = fn (int $n) => $this->edition()->events()->where('sort_order', $n)->with('sport', 'category')->firstOrFail();

        $this->assertSame(['puntualidad', null, 'none'], [$row(1)->sport->slug, $row(1)->category?->name, $row(1)->gender_scope->value]);
        $this->assertSame(['ciclismo', 'Libre', 'male'], [$row(10)->sport->slug, $row(10)->category->name, $row(10)->gender_scope->value]);
        $this->assertSame(['futbol', 'Libre', 'male'], [$row(11)->sport->slug, $row(11)->category->name, $row(11)->gender_scope->value]);
        $this->assertSame(['futsal', 'Súper Máster', 'male'], [$row(14)->sport->slug, $row(14)->category->name, $row(14)->gender_scope->value]);
        $this->assertSame(['voley', 'Máster', 'both'], [$row(22)->sport->slug, $row(22)->category->name, $row(22)->gender_scope->value]);
        $this->assertSame(['ciclismo', 'female'], [$row(23)->sport->slug, $row(23)->gender_scope->value]);
        $this->assertSame(['futsal', 'Máster', 'female'], [$row(24)->sport->slug, $row(24)->category->name, $row(24)->gender_scope->value]);

        // S1: "(damas y varones)" = un podio por genero. S2: atletismo por prueba.
        $this->assertSame(0, $this->edition()->events()->where('separate_podiums', false)->count());
        $this->assertEqualsCanonicalizing([5, 6, 7], $this->edition()->events()->where('per_discipline', true)->pluck('sort_order')->all());
        $this->assertSame(0, $this->edition()->events()->where('rules_blocking', true)->count());
    }

    public function test_roster_limits_match_the_rules(): void
    {
        $limits = fn (string $slug) => GamesEditionEvent::whereHas('sport', fn ($q) => $q->where('slug', $slug))
            ->get()->map(fn ($e) => [$e->min_players, $e->max_players])->unique()->values()->all();

        $this->assertSame([[7, 18]], $limits('futbol'));
        $this->assertSame([[3, 10]], $limits('futsal'));
        $this->assertSame([[null, 10]], $limits('basquetbol'));
        $this->assertSame([[6, 12]], $limits('voley'));
        $this->assertSame([[null, 3]], $limits('tiro-al-sapo'));
        $this->assertSame([[null, 1]], $limits('ajedrez'));
        $this->assertSame([[null, 1]], $limits('tenis-de-mesa'));
        $this->assertSame([[null, 1]], $limits('ciclismo'));
        $this->assertSame([[10, null]], $limits('estudiantina'));
        $this->assertSame([[null, null]], $limits('danza'));
    }

    public function test_venues_are_loaded(): void
    {
        $this->assertEqualsCanonicalizing([
            'Plaza de Armas de Yunguyo',
            'Estadio José Carlos Mariátegui',
            'Complejo Deportivo',
            'Coliseo Cerrado de Yunguyo',
            'Complejo Deportivo Agua Dulce',
            'Ruta C.P. Chimbo – Yunguyo',
            'Teatro Municipal de Yunguyo',
            'Coliseo Municipal de Yunguyo',
        ], Venue::pluck('name')->all());
    }

    public function test_seeder_is_idempotent(): void
    {
        $counts = fn () => [
            GamesEdition::count(), GamesEditionGroup::count(), GamesEditionTeam::count(), Team::count(),
            GamesEditionEvent::count(), Venue::count(), Category::count(), Sport::count(), Discipline::count(),
        ];
        $before = $counts();

        // Un cambio del admin no se pisa al volver a sembrar.
        $this->edition()->events()->where('sort_order', 26)->update(['pts_first' => 15]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(Puno2026Seeder::class);

        $this->assertSame($before, $counts());
        $this->assertSame(15, $this->points(26)[0]);
    }

    /** La prueba con el nombre anterior se renombra; no queda duplicada. */
    public function test_old_discipline_name_is_renamed_instead_of_duplicated(): void
    {
        $atletismo = Sport::where('slug', 'atletismo')->firstOrFail();
        $discipline = Discipline::where('sport_id', $atletismo->id)->where('name', '100 metros')->firstOrFail();
        $discipline->update(['name' => '100 metros llanos']);
        $total = Discipline::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('100 metros', $discipline->fresh()->name);
        $this->assertSame($total, Discipline::count());
    }
}
