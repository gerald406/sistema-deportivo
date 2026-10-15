<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Livewire\DelegationEnrollment\Index as EnrollmentIndex;
use App\Livewire\Rosters\Index as RostersIndex;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Services\DelegationEnrollmentService;
use App\Services\SeasonTeamPlayerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** D22: el delegado inscribe a su delegacion en las disciplinas, hasta el cierre de inscripciones. */
class DelegationEnrollmentTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private User $organizer;

    private User $delegate;

    private GamesEdition $edition;

    private Team $team;

    private Season $football;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->organizer = $this->organizer();
        $this->edition = GamesEdition::factory()->create([
            'organizer_id' => $this->organizer->id, 'start_date' => '2026-10-28', 'registration_deadline' => '2026-10-23',
        ]);
        $this->delegate = $this->delegate();
        $this->team = Team::factory()->create(['name' => 'IESP Yunguyo']);
        $this->registerDelegation($this->edition, $this->team, $this->delegate);

        $this->football = $this->competition('Fútbol Libre Varones', 'head_to_head', max: 18);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function competition(string $name, string $format, ?int $max = null, ?int $min = null): Season
    {
        $sport = Sport::factory()->create(['name' => $name, 'format_type' => $format]);
        $event = GamesEditionEvent::factory()->create([
            'games_edition_id' => $this->edition->id, 'sport_id' => $sport->id, 'label' => $name,
            'min_players' => $min, 'max_players' => $max,
        ]);

        return Season::factory()->create([
            'games_edition_id' => $this->edition->id, 'sport_id' => $sport->id, 'name' => $name,
            'games_edition_event_id' => $event->id, 'status' => 'active', 'is_active' => true,
        ]);
    }

    private function service(): DelegationEnrollmentService
    {
        return app(DelegationEnrollmentService::class);
    }

    private function screen(?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->delegate)->test(EnrollmentIndex::class);
    }

    private function enrolled(): bool
    {
        return SeasonTeam::where('season_id', $this->football->id)->where('team_id', $this->team->id)->where('is_active', true)->exists();
    }

    public function test_delegate_enrolls_and_withdraws_from_the_screen(): void
    {
        $this->screen()
            ->assertSet('editionId', (string) $this->edition->id)
            ->assertSet('teamId', (string) $this->team->id)
            ->call('toggle', $this->football->id)
            ->assertDispatched('toast', type: 'success');

        // La inscripcion existe aunque aun no tenga participantes.
        $this->assertTrue($this->enrolled());
        $this->assertSame(0, SeasonTeam::where('season_id', $this->football->id)->sole()->roster()->count());

        $this->screen()->call('toggle', $this->football->id)->assertDispatched('toast', type: 'success');
        $this->assertFalse($this->enrolled());
    }

    public function test_withdrawing_needs_an_empty_roster(): void
    {
        $seasonTeam = $this->service()->enroll($this->football, $this->team, $this->delegate);
        $this->rosterEntry($seasonTeam, 9);

        $this->assertIsString($this->service()->withdraw($seasonTeam, $this->delegate));
        $this->assertTrue($this->enrolled());
    }

    public function test_delegate_only_enrolls_its_own_delegation(): void
    {
        $other = Team::factory()->create();
        $this->registerDelegation($this->edition, $other, $this->delegate());

        $this->expectException(AuthorizationException::class);
        $this->service()->enroll($this->football, $other, $this->delegate);
    }

    public function test_screen_only_offers_own_delegations_and_rejects_others_from_the_url(): void
    {
        $other = Team::factory()->create(['name' => 'Otra delegación']);
        $this->registerDelegation($this->edition, $other, $this->delegate());

        $screen = $this->screen();
        $this->assertSame([$this->team->id], $screen->instance()->teamOptions()->pluck('id')->all());

        // Delegacion ajena puesta a mano: no se acepta.
        $screen->set('teamId', (string) $other->id);
        $this->assertNull($screen->instance()->team());

        // El organizador ve todas.
        $this->assertCount(2, $this->screen($this->organizer)->instance()->teamOptions());
    }

    public function test_after_the_deadline_only_the_organizer_enrolls(): void
    {
        Carbon::setTestNow('2026-10-24 08:00:00');

        $this->screen()
            ->call('toggle', $this->football->id)
            ->assertDispatched('toast', type: 'error');
        $this->assertFalse($this->enrolled());

        $this->service()->enroll($this->football, $this->team, $this->organizer);
        $this->assertTrue($this->enrolled());

        // Tampoco retira despues del cierre.
        $this->expectException(ValidationException::class);
        $this->service()->withdraw(SeasonTeam::where('season_id', $this->football->id)->sole(), $this->delegate);
    }

    public function test_deadline_day_is_still_open(): void
    {
        Carbon::setTestNow('2026-10-23 23:30:00');

        $this->service()->enroll($this->football, $this->team, $this->delegate);
        $this->assertTrue($this->enrolled());
    }

    public function test_after_the_deadline_a_first_participant_cannot_open_a_new_discipline(): void
    {
        $chess = $this->competition('Ajedrez Libre Varones', 'head_to_head', max: 1);
        $this->service()->enroll($this->football, $this->team, $this->delegate);
        $data = fn () => ['player_id' => Player::factory()->create(['created_by' => $this->delegate->id, 'gender' => 'M'])->id,
            'shirt_number' => null, 'position_id' => null, 'is_captain' => false, 'status' => 'active', 'enrolled_at' => '2026-10-24'];

        Carbon::setTestNow('2026-10-24 08:00:00');
        $rosters = app(SeasonTeamPlayerService::class);

        // En una disciplina ya inscrita, si puede seguir agregando.
        $rosters->registerInCompetition($this->football, $this->team, $data(), $this->delegate);
        $this->addToAssertionCount(1);

        try {
            $rosters->registerInCompetition($chess, $this->team, $data(), $this->delegate);
            $this->fail('El delegado abrió una disciplina nueva después del cierre.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('form.season_id', $e->errors());
        }
        $this->assertFalse(SeasonTeam::where('season_id', $chess->id)->exists());
    }

    public function test_inscription_disabled_by_the_organization_is_not_reactivated_by_the_delegate(): void
    {
        $seasonTeam = $this->service()->enroll($this->football, $this->team, $this->delegate);
        $seasonTeam->update(['is_active' => false]);

        try {
            $this->service()->enroll($this->football, $this->team, $this->delegate);
            $this->fail('El delegado reactivó una inscripción desactivada por la organización.');
        } catch (ValidationException) {
            $this->assertFalse($seasonTeam->refresh()->is_active);
        }

        $this->service()->enroll($this->football, $this->team, $this->organizer);
        $this->assertTrue($seasonTeam->refresh()->is_active);
    }

    public function test_withdrawn_delegation_cannot_enroll(): void
    {
        $this->registerDelegation($this->edition, $this->team, $this->delegate, attributes: ['status' => 'withdrawn']);

        // La pantalla no le ofrece la delegacion y el Service lo rechaza con el motivo (SeasonTeamService::ensureFor()).
        $this->assertNull($this->screen()->instance()->team());

        try {
            $this->service()->enroll($this->football, $this->team, $this->delegate);
            $this->fail('Una delegación retirada se inscribió en una disciplina.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('retirada', collect($e->errors())->flatten()->first());
        }
        $this->assertFalse($this->enrolled());
    }

    public function test_board_classifies_team_and_individual_disciplines(): void
    {
        $chess = $this->competition('Ajedrez Libre Damas', 'head_to_head', max: 1);
        $run = $this->competition('Atletismo 100 m', 'individual_ranked', max: 1);
        $choir = $this->competition('Estudiantina', 'individual_ranked', min: 10);

        $kinds = $this->service()->board($this->edition, $this->team)->mapWithKeys(fn ($r) => [$r->season->name => $r->kind])->all();

        $this->assertSame(DelegationEnrollmentService::TEAM, $kinds['Fútbol Libre Varones']);
        $this->assertSame(DelegationEnrollmentService::INDIVIDUAL, $kinds[$chess->name]);
        $this->assertSame(DelegationEnrollmentService::INDIVIDUAL, $kinds[$run->name]);
        $this->assertSame(DelegationEnrollmentService::TEAM, $kinds[$choir->name]);

        $this->assertSame(['Fútbol Libre Varones'], $this->screen()->set('kindFilter', 'team')->set('search', 'fút')->viewData('rows')->pluck('season.name')->all());
    }

    public function test_roster_link_opens_the_discipline_roster(): void
    {
        $seasonTeam = $this->service()->enroll($this->football, $this->team, $this->delegate);

        $this->screen()->assertSee(route('rosters.index', ['inscripcion' => $seasonTeam->id]), false);

        Livewire::withQueryParams(['inscripcion' => (string) $seasonTeam->id])
            ->actingAs($this->delegate)->test(RostersIndex::class)
            ->assertSet('seasonTeamFilter', (string) $seasonTeam->id)
            ->call('create')
            ->assertSet('form.season_id', $this->football->id)
            ->assertSet('form.team_id', $this->team->id);
    }

    public function test_page_is_reachable_by_the_three_roles(): void
    {
        foreach ([$this->delegate, $this->organizer, $this->admin()] as $user) {
            $this->actingAs($user)->get(route('enrollment.index'))->assertOk()->assertSee('Inscripción por disciplina');
        }
    }
}
