<?php

declare(strict_types=1);

namespace Tests\Feature\Exports;

use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Livewire\Admin\Sports\Index as SportsIndex;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\EditionEvents\Index as EditionEventsIndex;
use App\Livewire\EditionGroups\Index as EditionGroupsIndex;
use App\Livewire\EditionTeams\Index as EditionTeamsIndex;
use App\Livewire\GamesEditions\Index as GamesEditionsIndex;
use App\Livewire\Matchdays\Index as MatchdaysIndex;
use App\Livewire\Matches\Index as MatchesIndex;
use App\Livewire\MatchEvents\Index as MatchEventsIndex;
use App\Livewire\Periods\Index as PeriodsIndex;
use App\Livewire\Players\Index as PlayersIndex;
use App\Livewire\Relays\Index as RelaysIndex;
use App\Livewire\ReopenLogs\Index as ReopenLogsIndex;
use App\Livewire\Reports\DelegationRoster;
use App\Livewire\Reports\DisciplineRoster;
use App\Livewire\Reports\GeneralScore;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Results\Index as ResultsIndex;
use App\Livewire\Rosters\Index as RostersIndex;
use App\Livewire\Seasons\Index as SeasonsIndex;
use App\Livewire\SeasonStages\Index as SeasonStagesIndex;
use App\Livewire\SeasonTeams\Index as SeasonTeamsIndex;
use App\Livewire\Suspensions\Index as SuspensionsIndex;
use App\Livewire\Teams\Index as TeamsIndex;
use App\Livewire\Venues\Index as VenuesIndex;
use App\Models\Category;
use App\Models\EventType;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\MatchEvent;
use App\Models\MatchPeriod;
use App\Models\MatchReopenLog;
use App\Models\SeasonStage;
use App\Models\Suspension;
use App\Models\Team;
use App\Models\Venue;
use App\Services\ExcelExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * Boton "Excel" de los listados: el archivo es un .xlsx valido, trae todas
 * las filas filtradas (no solo la pagina) y nunca filas fuera del alcance
 * del usuario.
 */
class ExcelExportTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    /** @return list<list<mixed>> filas del .xlsx, encabezados incluidos */
    private function readXlsx(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $binary);

        $reader = new Reader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }

        $reader->close();
        unlink($path);

        return $rows;
    }

    /** @return list<list<mixed>> */
    private function exportedRows(Testable $component): array
    {
        $component->call('export')->assertFileDownloaded();

        $this->assertStringEndsWith('.xlsx', $component->effects['download']['name']);

        return $this->readXlsx(base64_decode($component->effects['download']['content']));
    }

    public function test_service_writes_headings_rows_and_never_formulas(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        app(ExcelExportService::class)->write($path, 'Sedes: [2026]', [
            'Nombre' => fn (array $r) => $r['name'],
            'Partidos' => fn (array $r) => $r['matches'],
            'Activa' => fn (array $r) => $r['active'],
            'Alta' => fn (array $r) => $r['date'],
        ], [
            ['name' => '=1+1', 'matches' => 3, 'active' => true, 'date' => now()->setDate(2026, 10, 28)],
            ['name' => 'Estadio', 'matches' => 0, 'active' => false, 'date' => null],
        ]);

        $rows = $this->readXlsx(file_get_contents($path));
        unlink($path);

        $this->assertSame(['Nombre', 'Partidos', 'Activa', 'Alta'], $rows[0]);
        // "=1+1" llega como texto, no como formula evaluada.
        $this->assertSame(['=1+1', 3, 'Sí', '28/10/2026'], $rows[1]);
        $this->assertSame(['Estadio', 0, 'No', ''], $rows[2]);
    }

    public function test_every_listing_exports_its_rows_for_the_admin(): void
    {
        $admin = $this->admin();
        $c = $this->buildCompetition();

        Category::create(['name' => 'Libre', 'gender' => 'X', 'is_active' => true]);
        Venue::factory()->create();
        GamesEditionGroup::create(['games_edition_id' => $c->edition->id, 'name' => 'Serie A', 'sort_order' => 1]);
        SeasonStage::factory()->create(['season_id' => $c->season->id]);
        MatchPeriod::create(['match_id' => $c->match->id, 'period_number' => 1, 'home_points' => 25, 'away_points' => 20]);
        $type = EventType::firstOrCreate(['sport_id' => $c->season->sport_id, 'code' => 'goal'], ['name' => 'Gol']);
        MatchEvent::create(['match_id' => $c->match->id, 'season_team_player_id' => $c->homeEntry->id, 'event_type_id' => $type->id, 'minute' => 10]);
        Suspension::create(['season_id' => $c->season->id, 'season_team_player_id' => $c->homeEntry->id, 'match_id' => $c->match->id, 'reason' => 'Roja directa', 'matches_suspended' => 1, 'is_served' => false]);
        MatchReopenLog::create(['match_id' => $c->match->id, 'user_id' => $admin->id, 'previous_status' => 'played', 'previous_state' => ['participants' => []], 'events_deleted' => 0, 'suspensions_deleted' => 0, 'reason' => 'Error al digitar']);

        // pantalla => parametros de la URL; todas deben traer al menos una fila de datos.
        $screens = [
            TeamsIndex::class => [],
            PlayersIndex::class => [],
            SportsIndex::class => [],
            UsersIndex::class => [],
            RolesIndex::class => [],
            CategoriesIndex::class => [],
            VenuesIndex::class => [],
            GamesEditionsIndex::class => [],
            EditionGroupsIndex::class => [],
            EditionTeamsIndex::class => [],
            EditionEventsIndex::class => [],
            SeasonsIndex::class => [],
            SeasonTeamsIndex::class => [],
            RostersIndex::class => [],
            SeasonStagesIndex::class => ['seasonId' => (string) $c->season->id],
            MatchdaysIndex::class => [],
            MatchesIndex::class => [],
            ResultsIndex::class => [],
            PeriodsIndex::class => [],
            MatchEventsIndex::class => [],
            SuspensionsIndex::class => [],
            ReopenLogsIndex::class => [],
            GeneralScore::class => [],
            DelegationRoster::class => [],
            DisciplineRoster::class => [],
        ];

        foreach ($screens as $screen => $params) {
            $component = Livewire::actingAs($admin)->test($screen);

            foreach ($params as $property => $value) {
                $component->set($property, $value);
            }

            $rows = $this->exportedRows($component);

            $this->assertGreaterThanOrEqual(2, count($rows), "{$screen} no exporto filas de datos.");
            $this->assertNotContains('', $rows[0], "{$screen} tiene un encabezado vacio.");
        }

        // Sin postas ni tabla calculada: el archivo sale igual, solo con encabezados.
        foreach ([RelaysIndex::class, ReportsIndex::class] as $screen) {
            $this->assertCount(1, $this->exportedRows(Livewire::actingAs($admin)->test($screen)), $screen);
        }
    }

    public function test_export_brings_every_filtered_row_not_only_the_visible_page(): void
    {
        $admin = $this->admin();
        Team::factory()->count(12)->sequence(fn ($s) => ['name' => 'Colegio '.str_pad((string) $s->index, 2, '0', STR_PAD_LEFT)])->create();
        Team::factory()->create(['name' => 'Instituto Aparte']);

        $rows = $this->exportedRows(
            Livewire::actingAs($admin)->test(TeamsIndex::class)
                ->set('perPage', 10)
                ->set('search', 'Colegio')
                ->set('sortDirection', 'desc')
        );

        $names = array_column(array_slice($rows, 1), 0);

        $this->assertCount(12, $names);
        $this->assertSame('Colegio 11', $names[0]);
        $this->assertNotContains('Instituto Aparte', $names);
    }

    public function test_organizer_never_exports_delegations_outside_their_scope(): void
    {
        $organizer = $this->organizer();
        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $own = Team::factory()->create(['name' => 'Delegación Propia']);
        $this->registerDelegation($edition, $own);
        Team::factory()->create(['name' => 'Delegación Ajena']);

        $names = array_column(array_slice($this->exportedRows(Livewire::actingAs($organizer)->test(TeamsIndex::class)), 1), 0);

        $this->assertSame(['Delegación Propia'], $names);
    }

    public function test_export_reauthorizes_on_every_call(): void
    {
        $organizer = $this->organizer();
        $component = Livewire::actingAs($organizer)->test(TeamsIndex::class);

        // Pierde el rol con la pantalla ya abierta: export() no confia en mount().
        $organizer->syncRoles(['delegado']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $component->call('export')->assertForbidden();
    }
}
