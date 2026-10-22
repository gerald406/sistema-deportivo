<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Livewire\Public\PublicResults;
use App\Models\Category;
use App\Models\Discipline;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\Matchday;
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
 * competencias visibles, con la serie, la fecha, el deporte, la prueba, el
 * nivel, el genero y la delegacion como filtros, sin login, y nada fuera de
 * eso aunque se manipulen los filtros.
 */
class PublicPageTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    /** Otro partido entre dos delegaciones nuevas, de la competencia base o de $season. */
    private function extraMatch(object $c, string $home, string $away, array $attributes = [], ?Season $season = null): GameMatch
    {
        $season ??= $c->season;
        $matchday = $season->is($c->season) ? $c->matchday : Matchday::firstOrCreate(['season_id' => $season->id, 'name' => 'Fecha 1']);

        $match = GameMatch::create($attributes + [
            'season_id' => $season->id,
            'matchday_id' => $matchday->id,
            'round_type' => 'regular',
            'status' => 'pending',
            'scheduled_at' => '2026-10-29 10:00:00',
        ]);

        foreach (['home' => $home, 'away' => $away] as $side => $name) {
            $team = Team::factory()->create(['name' => $name]);
            $this->registerDelegation($c->edition, $team);
            $entry = SeasonTeam::create(['season_id' => $season->id, 'team_id' => $team->id, 'is_active' => true]);
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
        $component->assertSee($c->homeTeam->name);

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

        $component->set('search', '')->set('sportId', (string) $hidden->sport_id)->set('categoryId', '999999')->set('teamId', '999999');
        $this->assertEqualsCanonicalizing([$c->match->id, $other->id], $this->shownIds($component));
        $this->assertSame([], $component->viewData('activeFilters'));

        $component->set('gamesEditionId', (string) $hidden->games_edition_id);
        $this->assertSame($c->edition->id, $component->viewData('edition')->id);

        $component->set('search', 'no existe esta delegación')->assertSee('Ningún partido coincide con los filtros')
            ->call('clearFilters')->assertSet('search', '');
    }

    public function test_series_tab_shows_one_card_per_series_plus_finals_with_their_counts(): void
    {
        $c = $this->buildCompetition();
        $s = $this->withSeries($c);
        $c->match->update(['season_stage_id' => $s->stageA->id, 'scheduled_at' => now()->setTime(9, 0)]);
        $played = $this->extraMatch($c, 'Colegio Jugado', 'Colegio Rival', ['season_stage_id' => $s->stageA->id]);
        app(ResultService::class)->close($played, ['home' => 2, 'away' => 0], $c->organizer);
        $this->extraMatch($c, 'Colegio Final', 'Colegio Subcampeón', ['season_stage_id' => $s->final->id]);

        // La serie ya no es una puerta: la portada abre la programacion de todas.
        $component = Livewire::test(PublicResults::class)->assertSee('Colegio Final')->assertSee($c->homeTeam->name);
        $this->assertSame('programacion', $component->viewData('activeView'));
        $this->assertNull($component->viewData('card'));
        $this->assertSame(['programacion', 'resultados', 'podios', 'series'], array_keys($component->viewData('tabs')));

        $component->call('setView', 'series')
            ->assertSee('Serie A')->assertSee('Serie B')->assertSee('Finales y podios');
        $cards = $component->viewData('cards')->keyBy('key');

        $cardA = $cards[(string) $s->a->id];

        $this->assertSame([1, 1, 1], [$cardA['pending'], $cardA['played'], $cardA['today']]);
        $this->assertTrue($cardA['nextAt']->isToday());
        $this->assertSame([$c->homeTeam->name], $cardA['teams']->all());
        $this->assertSame(0, $cards[(string) $s->b->id]['pending']);
        $this->assertSame(1, $cards[PublicResultsService::FINALS]['pending']);
    }

    public function test_series_filter_narrows_the_list_and_foreign_series_are_ignored(): void
    {
        $c = $this->buildCompetition();
        $s = $this->withSeries($c);
        $c->match->update(['season_stage_id' => $s->stageA->id]);
        $inB = $this->extraMatch($c, 'Colegio B1', 'Colegio B2', ['season_stage_id' => $s->stageB->id]);
        $final = $this->extraMatch($c, 'Colegio F1', 'Colegio F2', ['season_stage_id' => $s->final->id]);
        $loose = $this->extraMatch($c, 'Colegio Suelto', 'Colegio Otro');

        $component = Livewire::test(PublicResults::class)->set('series', (string) $s->a->id);
        $this->assertSame([$c->match->id], $this->shownIds($component));
        $component->assertSee($c->homeTeam->name);
        $this->assertSame([['property' => 'series', 'label' => 'Serie A']], $component->viewData('activeFilters'));

        $component->set('series', (string) $s->b->id);
        $this->assertSame([$inB->id], $this->shownIds($component));

        // Finales: la fase sin serie y lo que no tiene fase.
        $component->set('series', PublicResultsService::FINALS);
        $this->assertEqualsCanonicalizing([$final->id, $loose->id], $this->shownIds($component));

        // Serie de otra olimpiada: se ignora y se ve todo.
        $foreign = GamesEditionGroup::create(['games_edition_id' => GamesEdition::factory()->create()->id, 'name' => 'Serie Z']);
        $component->set('series', (string) $foreign->id);
        $this->assertNull($component->viewData('card'));
        $this->assertCount(4, $this->shownIds($component));

        // Desde la pestaña Series: la tarjeta abre su programacion ya filtrada.
        $component->call('setView', 'series')->call('openSeries', (string) $s->b->id)
            ->assertSet('series', (string) $s->b->id)->assertSet('view', 'programacion');
        $this->assertSame([$inB->id], $this->shownIds($component));

        $component->call('clearFilters')->assertSet('series', '');
    }

    public function test_schedule_goes_from_the_latest_day_to_the_earliest_and_by_hour_within_a_day(): void
    {
        $c = $this->buildCompetition(); // 28/10 09:00
        $late = $this->extraMatch($c, 'Colegio Tarde', 'Colegio Rival', ['scheduled_at' => '2026-10-29 10:00:00']);
        $early = $this->extraMatch($c, 'Colegio Temprano', 'Colegio Otro', ['scheduled_at' => '2026-10-29 08:00:00']);
        $undated = $this->extraMatch($c, 'Colegio Sin Fecha', 'Colegio Más', ['scheduled_at' => null]);

        $component = Livewire::test(PublicResults::class);

        $this->assertSame([$early->id, $late->id, $c->match->id, $undated->id], $this->shownIds($component));
        $this->assertSame(['2026-10-29', '2026-10-28', PublicResultsService::NO_DATE], $component->viewData('strip')->pluck('key')->all());
        $this->assertSame([2, 1, 1], $component->viewData('strip')->pluck('count')->all());
    }

    public function test_date_strip_filters_by_day_and_ignores_unknown_dates(): void
    {
        $c = $this->buildCompetition();
        $other = $this->extraMatch($c, 'Colegio Otro Día', 'Colegio Rival');
        $undated = $this->extraMatch($c, 'Colegio Sin Fecha', 'Colegio Más', ['scheduled_at' => null]);

        $component = Livewire::test(PublicResults::class)->set('date', '2026-10-28');
        $this->assertSame([$c->match->id], $this->shownIds($component));
        $this->assertSame('2026-10-28', $component->viewData('activeDate'));

        $component->set('date', PublicResultsService::NO_DATE);
        $this->assertSame([$undated->id], $this->shownIds($component));

        // Un dia sin nada o un valor manipulado no filtra.
        foreach (['2026-01-01', "2026-10-28' OR 1=1"] as $value) {
            $component->set('date', $value);
            $this->assertEqualsCanonicalizing([$c->match->id, $other->id, $undated->id], $this->shownIds($component));
            $this->assertNull($component->viewData('activeDate'));
        }
    }

    public function test_level_gender_discipline_and_delegation_chips_narrow_the_list(): void
    {
        $c = $this->buildCompetition();
        $libre = Category::create(['name' => 'Libre', 'min_age' => 25, 'gender' => 'X', 'is_active' => true]);
        $master = Category::create(['name' => 'Máster', 'min_age' => 40, 'gender' => 'X', 'is_active' => true]);
        $sprint = Discipline::create(['sport_id' => $c->season->sport_id, 'name' => '100 metros', 'unit_of_measure' => 'time', 'better_direction' => 'asc', 'is_active' => true]);
        $long = Discipline::create(['sport_id' => $c->season->sport_id, 'name' => '1500 metros', 'unit_of_measure' => 'time', 'better_direction' => 'asc', 'is_active' => true]);
        $c->season->update(['category_id' => $libre->id, 'gender' => 'M', 'discipline_id' => $sprint->id]);
        $women = Season::factory()->create([
            'games_edition_id' => $c->edition->id, 'sport_id' => $c->season->sport_id,
            'category_id' => $master->id, 'gender' => 'F', 'discipline_id' => $long->id,
        ]);
        $womenMatch = $this->extraMatch($c, 'Colegio Damas', 'Colegio Damas Rival', [], $women);

        $component = Livewire::test(PublicResults::class);
        $this->assertSame(['Libre', 'Máster'], $component->viewData('categories')->pluck('name')->all());
        $this->assertSame(['M', 'F'], $component->viewData('genders')->map->value->all());
        $this->assertSame(2, $component->viewData('sportCounts')[$c->season->sport_id]['total']);

        $component->set('categoryId', (string) $master->id);
        $this->assertSame([$womenMatch->id], $this->shownIds($component));

        $component->set('categoryId', '')->set('gender', 'M');
        $this->assertSame([$c->match->id], $this->shownIds($component));

        // La prueba es del deporte elegido; cambiar de deporte la limpia.
        $component->set('gender', '')->set('sportId', (string) $c->season->sport_id)->set('disciplineId', (string) $long->id);
        $this->assertSame([$womenMatch->id], $this->shownIds($component));
        $component->set('sportId', '')->assertSet('disciplineId', '');

        // Delegacion: sus partidos, como local o visitante.
        $component->set('teamId', (string) $c->awayTeam->id);
        $this->assertSame([$c->match->id], $this->shownIds($component));
        $this->assertSame([['property' => 'teamId', 'label' => $c->awayTeam->name]], $component->viewData('activeFilters'));

        // Una delegacion que no es de esta olimpiada se ignora.
        $component->set('teamId', (string) Team::factory()->create()->id);
        $this->assertCount(2, $this->shownIds($component));
    }

    public function test_podium_state_and_delegation_filters(): void
    {
        $c = $this->buildCompetition();
        $decided = SeasonStage::create(['season_id' => $c->season->id, 'name' => 'Final A', 'mode' => 'direct_podium']);
        SeasonStageResult::create(['season_stage_id' => $decided->id, 'place' => 1, 'team_id' => $c->homeTeam->id, 'recorded_by' => $c->organizer->id]);
        $pending = SeasonStage::create(['season_id' => $c->season->id, 'name' => 'Final B', 'mode' => 'direct_podium', 'scheduled_at' => '2026-10-29 08:00:00']);
        $shown = fn ($component) => $component->viewData('podiums')->flatMap(fn (array $g) => $g['stages']->pluck('id'))->all();

        $component = Livewire::test(PublicResults::class)->call('setView', 'podios');
        $this->assertEqualsCanonicalizing([$decided->id, $pending->id], $shown($component));

        $component->set('state', PublicResultsService::DECIDED);
        $this->assertSame([$decided->id], $shown($component));

        $component->set('state', PublicResultsService::PENDING);
        $this->assertSame([$pending->id], $shown($component));

        $component->set('state', 'cualquier-cosa');
        $this->assertCount(2, $shown($component));

        // Delegacion: el podio en que figura y la prueba por disputar en que esta inscrita.
        $component->set('state', '')->set('teamId', (string) $c->awayTeam->id);
        $this->assertSame([$pending->id], $shown($component));
        $component->set('teamId', (string) $c->homeTeam->id);
        $this->assertEqualsCanonicalizing([$decided->id, $pending->id], $shown($component));
    }

    public function test_old_links_with_series_and_view_keep_working(): void
    {
        $c = $this->buildCompetition();
        $s = $this->withSeries($c);
        $final = SeasonStage::create(['season_id' => $c->season->id, 'name' => 'Final', 'mode' => 'direct_podium']);
        SeasonStageResult::create(['season_stage_id' => $final->id, 'place' => 1, 'team_id' => $c->homeTeam->id, 'recorded_by' => $c->organizer->id]);

        $component = Livewire::withQueryParams(['serie' => PublicResultsService::FINALS, 'vista' => 'podios'])
            ->test(PublicResults::class);

        $this->assertSame('podios', $component->viewData('activeView'));
        $this->assertSame(PublicResultsService::FINALS, $component->viewData('card')['key']);
        $component->assertSee('Final')->assertSee($c->homeTeam->name);
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

        $component = Livewire::test(PublicResults::class)->call('openSeries', (string) $s->a->id, 'podios');

        $this->assertSame('podios', $component->viewData('activeView'));
        $this->assertSame([$podiumA->id], $component->viewData('podiums')->flatMap(fn (array $g) => $g['stages']->pluck('id'))->all());
        $component->assertSee('Podio A')->assertSee($c->homeTeam->name);

        // Serie B tiene fase de podio pero sin resultados: la pestaña existe, vacia.
        $component->set('series', (string) $s->b->id)->assertSee('Ningún podio coincide con los filtros');

        $component->set('series', PublicResultsService::FINALS);
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
