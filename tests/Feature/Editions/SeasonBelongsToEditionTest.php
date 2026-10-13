<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\Sport;
use App\Services\GamesEditionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** F1: la competencia (Season) cuelga solo de la olimpiada; Torneo no existe. */
class SeasonBelongsToEditionTest extends TestCase
{
    use CreatesActors, RefreshDatabase;

    public function test_tournaments_table_is_gone(): void
    {
        $this->assertFalse(Schema::hasTable('tournaments'));
        $this->assertFalse(Schema::hasColumn('seasons', 'tournament_id'));
    }

    public function test_season_requires_an_edition(): void
    {
        $this->expectException(QueryException::class);

        Season::create([
            'sport_id' => Sport::factory()->create()->id,
            'name' => 'Sin olimpiada',
            'status' => 'draft',
        ]);
    }

    public function test_season_name_is_unique_within_an_edition(): void
    {
        $edition = GamesEdition::factory()->create();
        $other = GamesEdition::factory()->create();
        $sport = Sport::factory()->create();

        Season::factory()->create(['games_edition_id' => $edition->id, 'sport_id' => $sport->id, 'name' => 'Fútbol Libre']);

        // En otra olimpiada el mismo nombre es valido.
        Season::factory()->create(['games_edition_id' => $other->id, 'sport_id' => $sport->id, 'name' => 'Fútbol Libre']);
        $this->assertSame(2, Season::where('name', 'Fútbol Libre')->count());

        $this->expectException(QueryException::class);
        Season::factory()->create(['games_edition_id' => $edition->id, 'sport_id' => $sport->id, 'name' => 'Fútbol Libre']);
    }

    public function test_edition_with_seasons_cannot_be_deleted(): void
    {
        $edition = GamesEdition::factory()->create();
        Season::factory()->create(['games_edition_id' => $edition->id]);

        $result = app(GamesEditionService::class)->delete($edition);

        $this->assertIsString($result);
        $this->assertDatabaseHas('games_editions', ['id' => $edition->id]);
    }

    public function test_empty_edition_is_deleted(): void
    {
        $edition = GamesEdition::factory()->create();

        $this->assertTrue(app(GamesEditionService::class)->delete($edition));
        $this->assertDatabaseMissing('games_editions', ['id' => $edition->id]);
    }

    public function test_tournaments_permission_was_renamed(): void
    {
        $this->seedRoles();

        $this->assertTrue(Permission::where('name', 'editions.manage')->exists());
        $this->assertFalse(Permission::where('name', 'tournaments.manage')->exists());
    }
}
