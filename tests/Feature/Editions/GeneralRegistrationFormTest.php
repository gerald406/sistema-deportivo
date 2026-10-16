<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Livewire\PlayerRegistrationSelector;
use App\Livewire\Teams\Index as TeamsIndex;
use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\GeneralRegistrationFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** D21: ficha de inscripcion general por delegacion y disciplina (bases VII.c y VII.d). */
class GeneralRegistrationFormTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private User $organizer;

    private User $delegate;

    private GamesEdition $edition;

    private Team $team;

    private SeasonTeam $football;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = $this->organizer();
        $this->edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer->id, 'start_date' => '2026-10-28']);
        $this->delegate = $this->delegate(['name' => 'Delegado Titular']);
        $this->team = Team::factory()->create(['name' => 'IESP Yunguyo', 'director_name' => 'Mg. Ana Quispe']);
        $this->registerDelegation($this->edition, $this->team, $this->delegate, $this->delegate(['name' => 'Delegada Accesitaria']));

        $this->football = $this->enrollment('Fútbol Libre Varones');
    }

    private function enrollment(string $name, ?Team $team = null): SeasonTeam
    {
        $season = Season::firstOrCreate(
            ['games_edition_id' => $this->edition->id, 'name' => $name],
            Season::factory()->make(['games_edition_id' => $this->edition->id, 'name' => $name])->getAttributes(),
        );

        return SeasonTeam::create(['season_id' => $season->id, 'team_id' => ($team ?? $this->team)->id, 'is_active' => true]);
    }

    public function test_form_lists_the_whole_roster_with_the_delegate_signature_and_the_declaration(): void
    {
        $this->rosterEntry($this->football, 9, ['last_name' => 'Zapana', 'first_name' => 'Luis', 'birth_date' => '1990-12-31', 'dni' => '45531234']);
        $this->rosterEntry($this->football, 10, ['last_name' => 'Apaza', 'first_name' => 'Juan', 'birth_date' => '1980-01-01', 'dni' => '01234567']);
        $this->rosterEntry($this->football, 11, ['last_name' => 'Baja', 'first_name' => 'Pedro'])->update(['status' => 'inactive']);

        $form = app(GeneralRegistrationFormService::class)->prepare($this->football);

        // D30: N.o, APELLIDOS Y NOMBRES en mayuscula, DNI con los 4 ultimos ocultos y edad
        // (ano de la olimpiada - ano de nacimiento: 2026 - 1990). Por apellidos, sin bajas.
        $this->assertSame([
            ['number' => 1, 'name' => 'APAZA, JUAN', 'dni' => '0123****', 'age' => 46],
            ['number' => 2, 'name' => 'ZAPANA, LUIS', 'dni' => '4553****', 'age' => 36],
        ], $form['participants']->all());
        $this->assertSame(['Delegado Titular', 'Delegada Accesitaria'], [$form['delegateName'], $form['substituteName']]);
        $this->assertSame('dos (2)', $form['participantsInWords']);

        $response = $this->actingAs($this->delegate)->get(route('registrations.general', $this->football))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_view_renders_two_copies_with_only_the_delegate_signature(): void
    {
        foreach (range(1, 6) as $shirt) {
            $this->rosterEntry($this->football, $shirt, ['dni' => '4553'.str_pad((string) $shirt, 4, '0', STR_PAD_LEFT)]);
        }
        $form = app(GeneralRegistrationFormService::class)->prepare($this->football);

        $html = view('reports.pdf.ficha-general', ['forms' => collect([$form])])->render();

        $this->assertSame(2, substr_count($html, 'class="sheet"'));
        $this->assertStringContainsString('ORIGINAL', $html);
        $this->assertStringContainsString('COPIA', $html);

        // Solo las cuatro columnas: el DNI completo y los otros datos no salen.
        $this->assertStringContainsString('4553****', $html);
        $this->assertStringNotContainsString('45530001', $html);
        foreach (['F. nac.', 'Condición', 'Cap.'] as $removed) {
            $this->assertStringNotContainsString($removed, $html);
        }

        // Declaracion jurada (bases VII.d) con el numero en letras y la disciplina en mayuscula,
        // comparada como texto visible (sin etiquetas ni saltos de linea de la plantilla).
        $text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html)));
        $this->assertStringContainsString('DECLARACIÓN JURADA DE LA DIRECCIÓN (BASES, VII.D). El(la) Director(a) o los(las) Directores(as) de las instituciones que integran la delegación, de común acuerdo, otorgan representación al delegado designado para que, en nombre de la delegación, declare bajo juramento que los seis (6) participantes consignados en la presente ficha pertenecen a las instituciones que representan y gozan de buena salud para participar en la disciplina de FÚTBOL LIBRE VARONES de los '.$this->edition->name.'. Asimismo, el delegado asume la responsabilidad de consolidar, verificar y presentar la información consignada en la ficha, garantizando su veracidad, en coordinación con los directores de las respectivas instituciones.', $text);

        // Una sola firma por copia: la del delegado; la del director(a) ya no va.
        $this->assertSame(2, substr_count($html, 'class="sign-role"'));
        $this->assertStringNotContainsString('Mg. Ana Quispe', $html);
        $this->assertStringNotContainsString('Firma y sello', $html);
    }

    public function test_participants_count_in_spanish_words(): void
    {
        $words = fn (int $n) => app(GeneralRegistrationFormService::class)->countInWords($n);

        $this->assertSame(
            ['un (1)', 'seis (6)', 'quince (15)', 'veintiún (21)', 'treinta y dos (32)', 'cien (100)', 'ciento veinte (120)'],
            array_map($words, [1, 6, 15, 21, 32, 100, 120]),
        );
    }

    public function test_only_its_delegation_and_the_organization_print_it(): void
    {
        $this->rosterEntry($this->football, 9);
        $url = route('registrations.general', $this->football);

        foreach ([$this->delegate, $this->organizer, $this->admin()] as $allowed) {
            $this->actingAs($allowed)->get($url)->assertOk();
        }

        // Delegado de otra delegacion de la misma olimpiada, y organizador ajeno.
        $other = $this->delegate();
        $this->registerDelegation($this->edition, Team::factory()->create(), $other);

        foreach ([$other, $this->organizer()] as $stranger) {
            $this->actingAs($stranger)->get($url)->assertForbidden();
        }
    }

    public function test_empty_roster_has_no_form(): void
    {
        $this->actingAs($this->delegate)->get(route('registrations.general', $this->football))->assertNotFound();
    }

    public function test_all_forms_of_a_delegation_in_one_pdf(): void
    {
        $this->rosterEntry($this->football, 9);
        $volley = $this->enrollment('Vóley Libre Varones');
        $this->rosterEntry($volley, 4);
        $this->enrollment('Ajedrez Libre Varones'); // sin plantel: no sale

        $this->assertSame(
            ['Fútbol Libre Varones', 'Vóley Libre Varones'],
            app(GeneralRegistrationFormService::class)->seasonTeamsOf($this->edition, $this->team)->pluck('season.name')->all(),
        );

        $url = route('registrations.general.delegation', [$this->edition, $this->team]);
        $this->actingAs($this->delegate)->get($url)->assertOk();

        $other = $this->delegate();
        $this->registerDelegation($this->edition, Team::factory()->create(), $other);
        $this->actingAs($other)->get($url)->assertForbidden();
    }

    public function test_selector_offers_the_general_forms_of_the_delegation(): void
    {
        $this->rosterEntry($this->football, 9);
        $this->enrollment('Ajedrez Libre Varones');

        $groups = Livewire::actingAs($this->delegate)->test(PlayerRegistrationSelector::class)
            ->set('gamesEditionId', (string) $this->edition->id)
            ->assertSee('Fichas de inscripción general por disciplina')
            ->assertSee(route('registrations.general.delegation', [$this->edition, $this->team]), false)
            ->instance()->generalForms();

        $this->assertSame([$this->team->id], $groups->keys()->all());
        $this->assertSame(['Fútbol Libre Varones'], $groups[$this->team->id]->pluck('season.name')->all());
    }

    public function test_selector_only_lists_competitions_with_participants(): void
    {
        $this->rosterEntry($this->football, 9);
        $this->enrollment('Ajedrez Libre Varones');                                         // marcada, sin plantel
        $this->rosterEntry($this->enrollment('Vóley Libre Damas'), 1)->update(['status' => 'inactive']); // solo bajas
        $disabled = $this->enrollment('Básquet Libre Varones');
        $this->rosterEntry($disabled, 4);
        $disabled->update(['is_active' => false]);                                          // desactivada por la organizacion

        $seasons = Livewire::actingAs($this->delegate)->test(PlayerRegistrationSelector::class)
            ->set('gamesEditionId', (string) $this->edition->id)
            ->instance()->seasons();

        $this->assertSame(['Fútbol Libre Varones'], $seasons->pluck('name')->all());
    }

    public function test_delegate_does_not_choose_the_team(): void
    {
        $this->rosterEntry($this->football, 9);

        Livewire::actingAs($this->delegate)->test(PlayerRegistrationSelector::class)
            ->set('gamesEditionId', (string) $this->edition->id)
            ->assertSet('seasonTeamId', '')
            ->assertSee('IESP Yunguyo')
            ->assertDontSee('-- Todos los equipos --')
            ->set('seasonId', (string) $this->football->season_id)
            ->assertSet('seasonTeamId', (string) $this->football->id);
    }

    public function test_organizer_still_chooses_among_delegations(): void
    {
        $this->rosterEntry($this->football, 9);
        $other = Team::factory()->create(['name' => 'ISP Juli']);
        $this->registerDelegation($this->edition, $other);
        $this->rosterEntry($this->enrollment('Fútbol Libre Varones', $other), 10);

        $screen = Livewire::actingAs($this->organizer)->test(PlayerRegistrationSelector::class)
            ->set('gamesEditionId', (string) $this->edition->id)
            ->set('seasonId', (string) $this->football->season_id)
            ->assertSet('seasonTeamId', '')
            ->assertSee('-- Todos los equipos --');

        $this->assertCount(2, $screen->instance()->seasonTeams());
    }

    public function test_organizer_records_the_director_on_the_delegation(): void
    {
        Livewire::actingAs($this->admin())->test(TeamsIndex::class)
            ->call('edit', $this->team->id)
            ->assertSet('form.director_name', 'Mg. Ana Quispe')
            ->set('form.director_name', '  Dr. Luis Mamani ')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Dr. Luis Mamani', $this->team->refresh()->director_name);

        Livewire::actingAs($this->admin())->test(TeamsIndex::class)
            ->call('edit', $this->team->id)
            ->set('form.director_name', '')
            ->call('save');
        $this->assertNull($this->team->refresh()->director_name);
    }
}
