<?php

declare(strict_types=1);

namespace Tests\Feature\Competitions;

use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\StaffType;
use App\Livewire\Players\Index as PlayersIndex;
use App\Livewire\Rosters\Index as RostersIndex;
use App\Models\Category;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Sport;
use App\Models\Suspension;
use App\Models\Team;
use App\Models\User;
use App\Services\PlayerRegistrationService;
use App\Services\RosterRuleService;
use App\Services\SeasonTeamPlayerService;
use App\Services\SeasonTeamService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * F4: reglas de plantel de las bases como datos (D10, D11, S3). Niveles por
 * anio de nacimiento, genero, topes y una sola delegacion por participante.
 */
class RosterRuleTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private GamesEdition $edition;

    private User $organizer;

    private Team $team;

    private User $delegate;

    /** @var array<string, Category> */
    private array $levels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = $this->organizer();
        $this->edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer->id, 'start_date' => '2026-10-28']);
        $this->delegate = $this->delegate();
        $this->team = Team::factory()->create();
        $this->registerDelegation($this->edition, $this->team, $this->delegate);

        foreach (['Libre' => 25, 'Máster' => 40, 'Súper Máster' => 55] as $name => $minAge) {
            $this->levels[$name] = Category::create(['name' => $name, 'min_age' => $minAge, 'gender' => 'X', 'is_active' => true]);
        }
    }

    /**
     * Competencia con su fila del cuadro. La delegacion de la prueba ya la
     * marco en Inscripcion por disciplina (sin eso, el plantel no la admite).
     */
    private function competition(string $level = 'Libre', ?string $gender = 'M', array $row = [], ?string $sportName = null): Season
    {
        $sport = Sport::factory()->create($sportName ? ['name' => $sportName, 'slug' => str($sportName)->slug()->value()] : []);
        $event = GamesEditionEvent::factory()->create($row + [
            'games_edition_id' => $this->edition->id,
            'sport_id' => $sport->id,
            'category_id' => $this->levels[$level]->id,
        ]);

        $season = Season::factory()->create([
            'games_edition_id' => $this->edition->id,
            'games_edition_event_id' => $event->id,
            'sport_id' => $sport->id,
            'category_id' => $this->levels[$level]->id,
            'gender' => $gender,
        ]);
        SeasonTeam::create(['season_id' => $season->id, 'team_id' => $this->team->id, 'category_id' => $season->category_id, 'is_active' => true]);

        return $season;
    }

    private function player(string $birthDate = '1990-05-10', ?string $gender = 'M', array $attributes = []): Player
    {
        // Registrado por el delegado de la delegacion: asi esta al alcance de
        // el, de su suplente y del organizador de la olimpiada.
        return Player::factory()->create($attributes + ['birth_date' => $birthDate, 'gender' => $gender, 'created_by' => $this->delegate->id]);
    }

    /** @return list<string> */
    private function violations(Season $season, Player $player, ?Team $team = null): array
    {
        return app(RosterRuleService::class)->violations($season->fresh(), $team ?? $this->team, $player);
    }

    private function enroll(Season $season, Player $player, ?Team $team = null, ?User $actor = null, array $data = []): SeasonTeamPlayer
    {
        return app(SeasonTeamPlayerService::class)->registerInCompetition(
            $season, $team ?? $this->team, $data + ['player_id' => $player->id], $actor ?? $this->organizer,
        );
    }

    // ── 4.2 Datos del participante ──────────────────────────────────────

    public function test_delegate_registers_participant_with_gender_and_staff_data(): void
    {
        $player = app(PlayerRegistrationService::class)->register([
            'dni' => '12345678', 'first_name' => 'Ana', 'last_name' => 'Quispe', 'birth_date' => '1985-03-02',
            'gender' => 'F', 'staff_type' => 'teacher', 'employment_type' => 'contracted', 'is_active' => true,
        ], $this->delegate);

        $player->refresh();
        $this->assertSame(Gender::Female, $player->gender);
        $this->assertSame(StaffType::Teacher, $player->staff_type);
        $this->assertSame(EmploymentType::Contracted, $player->employment_type);
        $this->assertSame($this->delegate->id, $player->created_by);
        $this->assertNull($player->documents_verified_at);

        Livewire::actingAs($this->delegate)->test(PlayersIndex::class)
            ->call('create')
            ->set('form.dni', '87654321')->set('form.first_name', 'Luis')->set('form.last_name', 'Mamani')
            ->set('form.birth_date', '1980-01-01')
            ->call('save')
            ->assertHasErrors('form.gender');

        Livewire::actingAs($this->delegate)->test(PlayersIndex::class)
            ->call('create')
            ->set('form.dni', '87654321')->set('form.first_name', 'Luis')->set('form.last_name', 'Mamani')
            ->set('form.birth_date', '1980-01-01')->set('form.gender', 'M')
            ->set('form.staff_type', 'administrative')->set('form.employment_type', 'appointed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('players', ['dni' => '87654321', 'gender' => 'M', 'staff_type' => 'administrative', 'employment_type' => 'appointed']);
    }

    public function test_only_edition_manager_or_admin_marks_documents_verified(): void
    {
        $season = $this->competition();
        $player = $this->player(attributes: ['created_by' => $this->delegate->id]);
        $this->enroll($season, $player);
        $service = app(PlayerRegistrationService::class);

        // El delegado no puede, ni por el servicio ni colando el campo al editar.
        foreach ([$this->delegate, $this->organizer()] as $unauthorized) {
            try {
                $service->setDocumentsVerified($player, true, $unauthorized);
                $this->fail('Un usuario sin permiso marcó los documentos como verificados.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $service->update($player, ['first_name' => 'Otro', 'documents_verified_at' => now(), 'created_by' => 999]);
        $this->assertNull($player->refresh()->documents_verified_at);
        $this->assertSame($this->delegate->id, $player->created_by);

        Livewire::actingAs($this->delegate)->test(PlayersIndex::class)->call('toggleDocuments', $player->id)->assertForbidden();
        $this->assertNull($player->refresh()->documents_verified_at);

        // El organizador de la olimpiada si; y puede retirarla.
        Livewire::actingAs($this->organizer)->test(PlayersIndex::class)->call('toggleDocuments', $player->id)->assertDispatched('toast');
        $this->assertNotNull($player->refresh()->documents_verified_at);

        $service->setDocumentsVerified($player, false, $this->admin());
        $this->assertNull($player->refresh()->documents_verified_at);
    }

    // ── 4.3 Reglas del plantel ──────────────────────────────────────────

    public function test_age_is_computed_by_birth_year(): void
    {
        $season = $this->competition('Libre');

        // Olimpiada 2026, Libre 25+: entra quien nacio hasta 2001, aunque
        // cumpla los 25 despues del evento.
        $this->assertSame([], $this->violations($season, $this->player('2001-12-31')));

        $violations = $this->violations($season, $this->player('2002-01-01'));
        $this->assertCount(1, $violations);
        $this->assertSame('Nacido en 2002: tendría 24 en 2026; Libre exige 25.', $violations[0]);
    }

    public function test_reference_year_is_the_edition_year(): void
    {
        $season = $this->competition('Libre');
        $this->assertSame(2026, app(RosterRuleService::class)->referenceYear($season));

        $this->edition->update(['start_date' => '2027-03-01']);
        $this->assertSame([], $this->violations($season, $this->player('2002-01-01')));
    }

    public function test_older_participant_can_play_a_lower_level(): void
    {
        $veteran = $this->player('1970-06-15');

        foreach (['Libre', 'Máster', 'Súper Máster'] as $level) {
            $this->assertSame([], $this->violations($this->competition($level), $veteran), "Nivel {$level}.");
        }
    }

    public function test_master_and_super_master_thresholds(): void
    {
        $master = $this->competition('Máster');
        $this->assertSame([], $this->violations($master, $this->player('1986-12-31')));
        $this->assertCount(1, $this->violations($master, $this->player('1987-01-01')));

        $superMaster = $this->competition('Súper Máster');
        $this->assertSame([], $this->violations($superMaster, $this->player('1971-12-31')));
        $this->assertCount(1, $this->violations($superMaster, $this->player('1972-01-01')));
    }

    public function test_gender_must_match_the_competition(): void
    {
        $man = $this->player(gender: 'M');
        $woman = $this->player(gender: 'F');

        $violations = $this->violations($this->competition(gender: 'F'), $man);
        $this->assertSame(['La competencia es de Damas y el participante figura como varones.'], $violations);

        $this->assertSame([], $this->violations($this->competition(gender: 'F'), $woman));
        $this->assertSame([], $this->violations($this->competition(gender: 'X'), $man));
        $this->assertSame([], $this->violations($this->competition(gender: null), $woman));
    }

    public function test_missing_birth_date_or_gender_fails(): void
    {
        $season = $this->competition('Libre', 'M');

        $noBirth = Player::factory()->create(['birth_date' => null, 'gender' => 'M', 'created_by' => $this->delegate->id]);
        $this->assertCount(1, $this->violations($season, $noBirth));
        $this->assertStringContainsString('fecha de nacimiento', $this->violations($season, $noBirth)[0]);

        $noGender = $this->player(gender: null);
        $this->assertCount(1, $this->violations($season, $noGender));
        $this->assertStringContainsString('sin género registrado', $this->violations($season, $noGender)[0]);
    }

    public function test_max_players_is_enforced(): void
    {
        $season = $this->competition(row: ['max_players' => 3]);
        $entries = collect(range(1, 3))->map(fn () => $this->enroll($season, $this->player()));

        $fourth = $this->player();
        $this->assertSame(['El plantel ya tiene 3 de 3 permitidos.'], $this->violations($season, $fourth));

        // Los que ya estan dentro siguen en regla.
        $this->assertSame([], $this->violations($season, $entries[0]->player));

        // Una baja libera el cupo.
        $entries[0]->update(['status' => 'inactive']);
        $this->assertSame([], $this->violations($season, $fourth));

        // Otra delegacion tiene su propio cupo.
        $other = Team::factory()->create();
        $this->registerDelegation($this->edition, $other);
        $entries[0]->update(['status' => 'active']);
        $this->assertSame([], $this->violations($season, $fourth, $other));
    }

    public function test_one_delegation_per_participant_in_the_edition(): void
    {
        $other = Team::factory()->create(['name' => 'IESP Juli']);
        $this->registerDelegation($this->edition, $other);
        $football = $this->competition();
        $chess = $this->competition();
        $player = $this->player();

        $this->enroll($football, $player);

        $this->assertSame(["Ya está inscrito con {$this->team->name} en esta olimpiada."], $this->violations($chess, $player, $other));
        $this->assertSame([], $this->violations($chess, $player, $this->team));

        // En otra olimpiada no cuenta.
        $elsewhere = Season::factory()->create();
        $this->assertSame([], $this->violations($elsewhere, $player, $other));
    }

    public function test_same_participant_in_several_competitions_of_his_delegation(): void
    {
        $player = $this->player('1980-01-01');

        foreach ([$this->competition('Libre'), $this->competition('Libre'), $this->competition('Máster')] as $season) {
            $this->assertSame([], $this->violations($season, $player));
            $this->enroll($season, $player, actor: $this->delegate);
        }

        $this->assertSame(3, SeasonTeamPlayer::where('player_id', $player->id)->count());
        $this->assertSame(3, SeasonTeam::where('team_id', $this->team->id)->count());
    }

    public function test_non_blocking_rules_register_with_warnings(): void
    {
        $season = $this->competition('Máster', row: ['rules_blocking' => false]);
        $young = $this->player('2000-01-01');

        $result = app(RosterRuleService::class)->evaluate($season, $this->team, $young);
        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['warnings']);

        $entry = $this->enroll($season, $young, actor: $this->delegate);

        $this->assertDatabaseHas('season_team_player', ['id' => $entry->id, 'player_id' => $young->id, 'status' => 'active']);
    }

    public function test_blocking_rules_reject(): void
    {
        $season = $this->competition('Máster', row: ['rules_blocking' => true]);
        $young = $this->player('2000-01-01');

        $result = app(RosterRuleService::class)->evaluate($season, $this->team, $young);
        $this->assertCount(1, $result['errors']);
        $this->assertSame([], $result['warnings']);

        try {
            $this->enroll($season, $young, actor: $this->delegate);
            $this->fail('Se inscribió a un participante que incumple una regla bloqueante.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Máster exige 40', $e->errors()['form.player_id'][0]);
        }

        $this->assertSame(0, SeasonTeamPlayer::count());

        $this->assertNotNull($this->enroll($season, $this->player('1980-01-01'), actor: $this->delegate));
    }

    public function test_participants_join_the_marked_discipline_entry(): void
    {
        $season = $this->competition();

        $first = $this->enroll($season, $this->player(), actor: $this->delegate);
        $second = $this->enroll($season, $this->player(), actor: $this->delegate);

        $this->assertSame(1, SeasonTeam::count());
        $this->assertSame($first->season_team_id, $second->season_team_id);
        $this->assertDatabaseHas('season_team', ['season_id' => $season->id, 'team_id' => $this->team->id, 'is_active' => true]);
    }

    public function test_unregistered_or_disqualified_delegation_is_rejected(): void
    {
        $season = $this->competition();
        $service = app(SeasonTeamService::class);

        $outsider = Team::factory()->create();
        $retired = Team::factory()->create();
        $disqualified = Team::factory()->create();
        $this->registerDelegation($this->edition, $retired, attributes: ['status' => 'withdrawn']);
        $this->registerDelegation($this->edition, $disqualified, attributes: ['status' => 'disqualified']);

        foreach ([$outsider, $retired, $disqualified] as $team) {
            try {
                $service->ensureFor($season, $team);
                $this->fail('Se aceptó una delegación que no puede competir.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, SeasonTeam::whereIn('team_id', [$outsider->id, $retired->id, $disqualified->id])->count());
        $this->assertSame($this->team->id, $service->ensureFor($season, $this->team)->team_id);
    }

    public function test_closed_competition_admits_no_participants(): void
    {
        $season = $this->competition();
        $season->update(['status' => 'closed']);

        $this->expectException(ValidationException::class);
        $this->enroll($season, $this->player());
    }

    public function test_foreign_delegate_cannot_register_in_competition(): void
    {
        $season = $this->competition();
        $player = $this->player();

        // Delegado de otra delegacion, organizador de otra olimpiada y un
        // delegado de ESTA delegacion pero en otra olimpiada.
        $otherTeam = Team::factory()->create();
        $foreignDelegate = $this->delegate();
        $this->registerDelegation($this->edition, $otherTeam, $foreignDelegate);

        $elsewhereDelegate = $this->delegate();
        $this->registerDelegation(GamesEdition::factory()->create(), $this->team, $elsewhereDelegate);

        foreach ([$foreignDelegate, $elsewhereDelegate, $this->organizer()] as $stranger) {
            try {
                $this->enroll($season, $player, actor: $stranger);
                $this->fail('Un usuario ajeno inscribió a un participante.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, SeasonTeamPlayer::count());

        // El suplente si puede.
        $substitute = $this->delegate();
        $this->registerDelegation($this->edition, $this->team, $this->delegate, $substitute);
        $this->assertNotNull($this->enroll($season, $player, actor: $substitute));
    }

    public function test_suspended_participant_keeps_suspended_status(): void
    {
        $season = $this->competition();
        $entry = $this->enroll($season, $this->player());
        $matchday = Matchday::create(['season_id' => $season->id, 'name' => 'Fecha 1']);
        $match = GameMatch::create(['season_id' => $season->id, 'matchday_id' => $matchday->id, 'round_type' => 'regular', 'status' => 'pending']);
        Suspension::create(['season_id' => $season->id, 'season_team_player_id' => $entry->id, 'match_id' => $match->id, 'reason' => 'Roja directa', 'matches_suspended' => 1, 'is_served' => false]);
        $entry->update(['status' => 'suspended']);

        try {
            app(SeasonTeamPlayerService::class)->update($entry, ['status' => 'active', 'enrolled_at' => '2026-10-20']);
            $this->fail('Se habilitó a un participante con una sanción pendiente.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('form.status', $e->errors());
        }

        $this->assertSame('suspended', $entry->refresh()->status->value);
    }

    // ── Pantalla de planteles ───────────────────────────────────────────

    public function test_delegate_adds_a_participant_from_the_screen(): void
    {
        $season = $this->competition('Máster', 'M', ['min_players' => 3, 'max_players' => 10], 'Futsal');
        $player = $this->player('2000-01-01', 'M', ['created_by' => $this->delegate->id]);

        Livewire::actingAs($this->delegate)->test(RostersIndex::class)
            ->call('create')
            ->set('form.season_id', $season->id)
            ->assertSet('form.team_id', $this->team->id) // una sola delegacion: se elige sola
            ->call('selectPlayer', $player->id)
            ->set('form.shirt_number', 9)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'warning');

        $this->assertDatabaseHas('season_team_player', ['player_id' => $player->id, 'shirt_number' => 9]);

        // El listado muestra la advertencia de edad y el aviso de minimo.
        $this->actingAs($this->delegate)->get(route('rosters.index'))
            ->assertOk()
            ->assertSee('Máster exige 40')
            ->assertSee('Faltan 2 para el mínimo');
    }

    public function test_picker_only_offers_participants_of_own_delegations(): void
    {
        $season = $this->competition();
        $substitute = $this->delegate();
        $this->registerDelegation($this->edition, $this->team, $this->delegate, $substitute);

        $otherTeam = Team::factory()->create();
        $otherDelegate = $this->delegate();
        $this->registerDelegation($this->edition, $otherTeam, $otherDelegate);

        $mine = $this->player(attributes: ['last_name' => 'Zapana']);
        $bySubstitute = Player::factory()->create(['last_name' => 'Zapata', 'created_by' => $substitute->id]);
        $foreign = Player::factory()->create(['last_name' => 'Zapater', 'dni' => '70000001', 'created_by' => $otherDelegate->id]);

        $names = fn (User $actor, string $term) => Livewire::actingAs($actor)->test(RostersIndex::class)
            ->set('playerSearch', $term)->instance()->playerOptions()->pluck('id')->all();

        // Titular y suplente comparten sus participantes; el ajeno no aparece
        // ni por apellido ni por DNI.
        $this->assertEqualsCanonicalizing([$mine->id, $bySubstitute->id], $names($this->delegate, 'Zapa'));
        $this->assertEqualsCanonicalizing([$mine->id, $bySubstitute->id], $names($substitute, 'Zapa'));
        $this->assertSame([], $names($this->delegate, '70000001'));
        $this->assertSame([$foreign->id], $names($otherDelegate, 'Zapa'));

        // El organizador ve los de todas las delegaciones de su olimpiada; otro organizador, ninguno.
        $this->assertCount(3, $names($this->organizer, 'Zapa'));
        $this->assertSame([], $names($this->organizer(), 'Zapa'));

        // Forzar el id ajeno desde el navegador tampoco funciona.
        try {
            Livewire::actingAs($this->delegate)->test(RostersIndex::class)->call('selectPlayer', $foreign->id)->assertNotFound();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        try {
            $this->enroll($season, $foreign, actor: $this->delegate);
            $this->fail('Un delegado agregó a un participante de otra delegación.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('form.player_id', $e->errors());
        }

        $this->assertSame(0, SeasonTeamPlayer::count());
        $this->assertNotNull($this->enroll($season, $bySubstitute, actor: $this->delegate));
    }

    public function test_delegate_only_sees_competitions_and_delegations_of_his_editions(): void
    {
        $own = $this->competition(sportName: 'Voleibol');
        $foreign = Season::factory()->create(['name' => 'Competencia Ajena']);
        $otherTeam = Team::factory()->create();
        $this->registerDelegation($this->edition, $otherTeam);
        SeasonTeam::create(['season_id' => $own->id, 'team_id' => $otherTeam->id, 'is_active' => true]);

        $component = Livewire::actingAs($this->delegate)->test(RostersIndex::class)->call('create');

        $this->assertSame([$own->id], $component->instance()->competitionOptions()->pluck('id')->all());

        $component->set('form.season_id', $own->id);
        $this->assertSame([$this->team->id], $component->instance()->delegationOptions()->pluck('id')->all());

        // Forzar otra delegacion o una competencia ajena desde el navegador no pasa.
        $player = $this->player();
        Livewire::actingAs($this->delegate)->test(RostersIndex::class)
            ->call('create')
            ->set('form.season_id', $own->id)
            ->set('form.team_id', $otherTeam->id)
            ->set('form.player_id', $player->id)
            ->call('save')
            ->assertForbidden();

        Livewire::actingAs($this->delegate)->test(RostersIndex::class)
            ->call('create')
            ->set('form.season_id', $foreign->id)
            ->set('form.team_id', $this->team->id)
            ->set('form.player_id', $player->id)
            ->call('save')
            ->assertHasErrors('form.season_id');

        $this->assertSame(0, SeasonTeamPlayer::count());

        // El organizador elige cualquier delegacion inscrita en su olimpiada.
        $asOrganizer = Livewire::actingAs($this->organizer)->test(RostersIndex::class)->call('create')->set('form.season_id', $own->id);
        $this->assertEqualsCanonicalizing([$this->team->id, $otherTeam->id], $asOrganizer->instance()->delegationOptions()->pluck('id')->all());
    }
}
