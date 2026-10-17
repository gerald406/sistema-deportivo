<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Livewire\Public\PublicResults;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\SeasonStageResult;
use App\Models\SeasonTeam;
use App\Models\Sport;
use App\Models\Team;
use App\Services\PublicResultsService;
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
 * Pagina publica ("/"): programacion, resultados y podios de las
 * competencias visibles, recorridos por serie, sin login, y nada fuera de
 * eso aunque se manipulen los filtros.
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

    /** Series A y B, con una fase de partidos cada una y la "Fase final" sin serie. */
    private function withSeries(object $c): object
    {
        $a = GamesEditionGroup::create(['games_edition_id' => $c->edition->id, 'name' => 'Serie A', 'sort_order' => 1]);
        $b = GamesEditionGroup::create(['games_edition_id' => $c->edition->id, 'name' => 'Serie B', 'sort_order' => 2]);
        $stage = fn (string $name, ?GamesEditionGroup $group, int $order) => SeasonStage::create([
            'season_id' => $c->season->id, 'name' => $name, 'sort_order' => $order, 'mode' => 'matches', 'group_id' => $group?->id,
        ]);

        $this->registerDelegation($c->edition, $c->homeTeam, attributes: ['group_id' => $a->id]);

        return (object) ['a' => $a, 'b' => $b, 'stageA' => $stage('Serie A', $a, 1), 'stageB' => $stage('Serie B', $b, 2), 'final' => $stage('Fase final', null, 3)];
    }

    /** @return list<int> ids de los partidos que la pagina esta mostrando */
    private function shownIds($component): array
    {
        return $component->viewData('days')->flatMap(fn (array $day) => $day['rows']->pluck('id'))->all();
    }

    public function test_home_is_public_and_shows_only_schedule_and_results(): void
    {
        $c = $this->buildCompetition();

        // Sin series, la portada abre directamente la programacion de toda la olimpiada.
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
        $this->assertSame(['programacion' => 1, 'resultados' => 1, 'podios' => 0], $component->viewData('counts'));
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

        $component->set('search', '')->set('sportId', (string) $hidden->sport_id)->set('seasonId', (string) $hidden->id);
        $this->assertEqualsCanonicalizing([$c->match->id, $other->id], $this->shownIds($component));

        $component->set('gamesEditionId', (string) $hidden->games_edition_id);
        $this->assertSame($c->edition->id, $component->viewData('edition')->id);

        $component->set('search', 'no existe esta delegación')->assertSee('Ningún partido coincide con tu búsqueda')
            ->call('clearFilters')->assertSet('search', '');
    }

    public function test_home_shows_one_card_per_series_plus_finals_with_their_counts(): void
    {
        $c = $this->buildCompetition();
        $s = $this->withSeries($c);
        $c->match->update(['season_stage_id' => $s->stageA->id, 'scheduled_at' => now()->setTime(9, 0)]);
        $played = $this->extraMatch($c, 'Colegio Jugado', 'Colegio Rival', ['season_stage_id' => $s->stageA->id]);
        app(ResultService::class)->close($played, ['home' => 2, 'away' => 0], $c->organizer);
        $this->extraMatch($c, 'Colegio Final', 'Colegio Subcampeón', ['season_stage_id' => $s->final->id]);

        $component = Livewire::test(PublicResults::class)
            ->assertSee('Serie A')->assertSee('Serie B')->assertSee('Finales y podios')
            ->assertDontSee('Colegio Final');

        $this->assertNull($component->viewData('card'));
        $cards = $component->viewData('cards')->keyBy('key');

        $cardA = $cards[(string) $s->a->id];

        $this->assertSame([1, 1, 1], [$cardA['pending'], $cardA['played'], $cardA['today']]);
        $this->assertTrue($cardA['nextAt']->isToday());
        $this->assertSame([$c->homeTeam->name], $cardA['teams']->all());
        $this->assertSame(0, $cards[(string) $s->b->id]['pending']);
        $this->assertSame(1, $cards[PublicResultsService::FINALS]['pending']);
    }

    public function test_opening_a_series_shows_only_its_matches_and_foreign_series_are_ignored(): void
    {
        $c = $this->buildCompetition();
        $s = $this->withSeries($c);
        $c->match->update(['season_stage_id' => $s->stageA->id]);
        $inB = $this->extraMatch($c, 'Colegio B1', 'Colegio B2', ['season_stage_id' => $s->stageB->id]);
        $final = $this->extraMatch($c, 'Colegio F1', 'Colegio F2', ['season_stage_id' => $s->final->id]);
        $loose = $this->extraMatch($c, 'Colegio Suelto', 'Colegio Otro');

        $component = Livewire::test(PublicResults::class)->call('openSeries', (string) $s->a->id);
        $this->assertSame([$c->match->id], $this->shownIds($component));
        $component->assertSee($c->homeTeam->name)->assertDontSee('Colegio B1');

        $component->call('openSeries', (string) $s->b->id);
        $this->assertSame([$inB->id], $this->shownIds($component));

        // Finales: la fase sin serie y lo que no tiene fase.
        $component->call('openSeries', PublicResultsService::FINALS);
        $this->assertEqualsCanonicalizing([$final->id, $loose->id], $this->shownIds($component));

        // Serie de otra olimpiada: no se abre nada, se vuelve a las tarjetas.
        $foreign = GamesEditionGroup::create(['games_edition_id' => GamesEdition::factory()->create()->id, 'name' => 'Serie Z']);
        $component->call('openSeries', (string) $foreign->id);
        $this->assertNull($component->viewData('card'));

        $component->call('openSeries', (string) $s->a->id)->call('closeSeries')->assertSet('series', '');
    }

    public function test_podiums_tab_lists_direct_podiums_of_the_series(): void
    {
        $c = $this->buildCompetition();
        $s = $this->withSeries($c);
        $podiumA = SeasonStage::create(['season_id' => $c->season->id, 'name' => 'Podio A', 'mode' => 'direct_podium', 'group_id' => $s->a->id]);
        $podiumFinal = SeasonStage::create(['season_id' => $c->season->id, 'name' => 'Final', 'mode' => 'direct_podium']);
        SeasonStage::create(['season_id' => $c->season->id, 'name' => 'Podio B', 'mode' => 'direct_podium', 'group_id' => $s->b->id]);

        foreach ([1 => $c->homeTeam, 2 => $c->awayTeam] as $place => $team) {
            SeasonStageResult::create(['season_stage_id' => $podiumA->id, 'place' => $place, 'team_id' => $team->id, 'recorded_by' => $c->organizer->id]);
        }
        SeasonStageResult::create(['season_stage_id' => $podiumFinal->id, 'place' => 1, 'team_id' => $c->awayTeam->id, 'recorded_by' => $c->organizer->id]);

        $component = Livewire::test(PublicResults::class)->call('openSeries', (string) $s->a->id)->call('setView', 'podios');

        $this->assertSame('podios', $component->viewData('activeView'));
        $this->assertSame([$podiumA->id], $component->viewData('podiums')->flatMap(fn (array $g) => $g['stages']->pluck('id'))->all());
        $component->assertSee('Podio A')->assertSee($c->homeTeam->name);

        // Serie B tiene fase de podio pero sin resultados: la pestaña existe, vacia.
        $component->call('openSeries', (string) $s->b->id)->call('setView', 'podios')->assertSee('Todavía no hay podios');

        $component->call('openSeries', PublicResultsService::FINALS)->call('setView', 'podios');
        $this->assertSame([$podiumFinal->id], $component->viewData('podiums')->flatMap(fn (array $g) => $g['stages']->pluck('id'))->all());
    }

    public function test_matches_are_paginated_instead_of_endless(): void
    {
        $c = $this->buildCompetition();
        foreach (range(1, 24) as $i) {
            $this->extraMatch($c, "Colegio Local {$i}", "Colegio Visita {$i}", ['scheduled_at' => '2026-10-29 '.sprintf('%02d', 8 + intdiv($i, 4)).':00:00']);
        }

        $component = Livewire::test(PublicResults::class);
        $this->assertCount(20, $this->shownIds($component));
        $this->assertSame(25, $component->viewData('page')->total());

        $component->call('nextPage', PublicResults::PAGE_NAME);
        $this->assertCount(5, $this->shownIds($component));

        // Cambiar de pestaña o de filtro vuelve a la primera pagina.
        $component->call('setView', 'programacion');
        $this->assertSame(1, $component->viewData('page')->currentPage());
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

        $this->assertSame(['programacion' => 10, 'resultados' => 10, 'podios' => 0], $schedule->viewData('counts'));

        // El podio de la prueba individual sale ordenado por marca (menor tiempo primero).
        $podium = $schedule->call('setView', 'resultados')->set('search', 'Huanca')
            ->viewData('days')->first()['rows']->first()['podium'];

        $this->assertSame([1, 'Huanca Ticona, Wilber', '12.35'], [$podium[0]['position'], $podium[0]['name'], $podium[0]['mark']]);
    }
}
