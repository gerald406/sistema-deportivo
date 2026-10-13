<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Livewire\Public\PublicResults;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Sport;
use App\Models\Team;
use App\Services\ResultService;
use Database\Seeders\DemoPublicSeeder;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * Pagina publica ("/"): solo programacion y resultados de las competencias
 * visibles, sin login, y nada fuera de eso aunque se manipulen los filtros.
 */
class PublicPageTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    /** Otro partido de la misma competencia entre dos delegaciones nuevas. */
    private function extraMatch(object $c, string $home, string $away, array $attributes = []): GameMatch
    {
        $match = GameMatch::create($attributes + [
            'season_id' => $c->season->id,
            'matchday_id' => $c->matchday->id,
            'round_type' => 'regular',
            'status' => 'pending',
            'scheduled_at' => '2026-10-29 10:00:00',
        ]);

        foreach (['home' => $home, 'away' => $away] as $side => $name) {
            $team = Team::factory()->create(['name' => $name]);
            $this->registerDelegation($c->edition, $team);
            $entry = SeasonTeam::create(['season_id' => $c->season->id, 'team_id' => $team->id, 'is_active' => true]);
            EventParticipant::create(['match_id' => $match->id, 'participant_type' => 'season_team', 'participant_id' => $entry->id, 'side' => $side]);
        }

        return $match;
    }

    /** @return list<int> ids de los partidos que la pagina esta mostrando */
    private function shownIds($component): array
    {
        return $component->viewData('days')->flatMap(fn (array $day) => $day['rows']->pluck('id'))->all();
    }

    public function test_home_is_public_and_shows_only_schedule_and_results(): void
    {
        $c = $this->buildCompetition();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($c->edition->name)
            ->assertSee('Programación')
            ->assertSee('Resultados')
            ->assertSee($c->homeTeam->name)
            ->assertDontSee('Goleadores')
            ->assertDontSee('Posiciones')
            ->assertDontSee('Suspendidos');

        $this->get('/resultados-publicos')->assertRedirect(route('home'));
    }

    public function test_schedule_lists_pending_matches_and_results_lists_played_ones(): void
    {
        $c = $this->buildCompetition();
        $played = $this->extraMatch($c, 'Colegio Jugado', 'Colegio Rival');
        $canceled = $this->extraMatch($c, 'Colegio Cancelado', 'Colegio Otro', ['status' => 'canceled']);
        app(ResultService::class)->close($played, ['home' => 3, 'away' => 1], $c->organizer);

        $component = Livewire::test(PublicResults::class);

        $this->assertSame([$c->match->id], $this->shownIds($component));
        $component->assertSee($c->homeTeam->name)->assertDontSee('Colegio Jugado')->assertDontSee('Colegio Cancelado');

        $component->call('setView', 'resultados');

        $this->assertSame([$played->id], $this->shownIds($component));
        $row = $component->viewData('days')->first()['rows']->first();
        $this->assertSame(['3', '1', 'home'], [$row['homeScore'], $row['awayScore'], $row['winnerSide']]);
        $this->assertNotContains($canceled->id, $this->shownIds($component));
        $this->assertSame(['programacion' => 1, 'resultados' => 1], $component->viewData('counts'));
    }

    public function test_draft_competitions_and_inactive_editions_are_never_published(): void
    {
        $c = $this->buildCompetition();
        $c->season->update(['status' => 'draft']);

        Livewire::test(PublicResults::class)
            ->assertDontSee($c->homeTeam->name)
            ->assertSee('Todavía no hay una olimpiada publicada');

        $c->season->update(['status' => 'active']);
        $c->edition->update(['is_active' => false]);

        $this->get(route('home'))->assertOk()->assertDontSee($c->homeTeam->name);
    }

    public function test_filters_narrow_the_list_and_foreign_ids_are_ignored(): void
    {
        $c = $this->buildCompetition();
        $other = $this->extraMatch($c, 'Colegio Buscado', 'Colegio Rival');

        // Competencia en borrador de otra olimpiada: su id no debe publicar nada ni romper el filtro.
        $hidden = Season::factory()->create([
            'games_edition_id' => GamesEdition::factory()->create()->id,
            'sport_id' => Sport::factory()->create()->id,
            'status' => 'draft',
        ]);

        $component = Livewire::test(PublicResults::class)->set('search', 'Buscado');
        $this->assertSame([$other->id], $this->shownIds($component));

        $component->set('search', '')->set('seasonId', (string) $hidden->id)->set('sportId', (string) $hidden->sport_id);
        $this->assertEqualsCanonicalizing([$c->match->id, $other->id], $this->shownIds($component));

        $component->set('gamesEditionId', (string) $hidden->games_edition_id);
        $this->assertSame($c->edition->id, $component->viewData('edition')->id);

        $component->set('search', 'no existe esta delegación')->assertSee('Ningún partido coincide con tu búsqueda')
            ->call('clearFilters')->assertSet('search', '');
    }

    public function test_limit_coming_from_the_browser_is_capped(): void
    {
        $c = $this->buildCompetition();

        $component = Livewire::test(PublicResults::class)->set('limit', 1000000)->call('more');

        $this->assertLessThanOrEqual(400, $component->get('limit'));
        $this->assertSame([$c->match->id], $this->shownIds($component));
    }

    public function test_demo_seeder_fills_the_public_page_and_is_idempotent(): void
    {
        $this->seed([SportSeeder::class, DisciplineSeeder::class]);
        $this->admin();

        $this->seed(DemoPublicSeeder::class);
        $matches = GameMatch::count();
        $this->seed(DemoPublicSeeder::class);

        $this->assertSame(1, GamesEdition::where('name', DemoPublicSeeder::EDITION_NAME)->count());
        $this->assertSame($matches, GameMatch::count());

        $schedule = Livewire::test(PublicResults::class)->assertSee(DemoPublicSeeder::EDITION_NAME);
        $counts = $schedule->viewData('counts');

        $this->assertSame(['programacion' => 10, 'resultados' => 10], $counts);

        // El podio de la prueba individual sale ordenado por marca (menor tiempo primero).
        $podium = $schedule->call('setView', 'resultados')->set('search', 'Huanca')
            ->viewData('days')->first()['rows']->first()['podium'];

        $this->assertSame([1, 'Huanca Ticona, Wilber', '12.35'], [$podium[0]['position'], $podium[0]['name'], $podium[0]['mark']]);
    }
}
