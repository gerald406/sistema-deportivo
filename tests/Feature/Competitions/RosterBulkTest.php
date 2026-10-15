<?php

declare(strict_types=1);

namespace Tests\Feature\Competitions;

use App\Livewire\Rosters\Index as RostersIndex;
use App\Models\Category;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Sport;
use App\Models\SportPosition;
use App\Models\Team;
use App\Models\User;
use App\Services\SeasonTeamPlayerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** D23: agregar varios participantes al plantel y editar camiseta, posicion y capitan en linea. */
class RosterBulkTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private User $delegate;

    private GamesEdition $edition;

    private Team $team;

    private Season $volley;

    private SeasonTeam $seasonTeam;

    private GamesEditionEvent $row;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer()->id, 'start_date' => '2026-10-28']);
        $this->delegate = $this->delegate();
        $this->team = Team::factory()->create(['name' => 'IESP Yunguyo']);
        $this->registerDelegation($this->edition, $this->team, $this->delegate);

        $libre = Category::create(['name' => 'Libre', 'min_age' => 25, 'gender' => 'X', 'is_active' => true]);
        $sport = Sport::factory()->create(['name' => 'Voleibol', 'format_type' => 'head_to_head']);
        $this->row = GamesEditionEvent::factory()->create([
            'games_edition_id' => $this->edition->id, 'sport_id' => $sport->id, 'category_id' => $libre->id,
            'min_players' => 6, 'max_players' => 3, 'rules_blocking' => false,
        ]);
        $this->volley = Season::factory()->create([
            'games_edition_id' => $this->edition->id, 'sport_id' => $sport->id, 'category_id' => $libre->id,
            'games_edition_event_id' => $this->row->id, 'gender' => 'F', 'name' => 'Voleibol Libre Damas', 'status' => 'active',
        ]);
        $this->seasonTeam = SeasonTeam::create(['season_id' => $this->volley->id, 'team_id' => $this->team->id, 'is_active' => true]);
    }

    private function woman(string $lastName, string $birthDate = '1985-03-01', array $attributes = []): Player
    {
        return Player::factory()->create($attributes + [
            'last_name' => $lastName, 'gender' => 'F', 'birth_date' => $birthDate, 'created_by' => $this->delegate->id, 'is_active' => true,
        ]);
    }

    private function screen()
    {
        return Livewire::withQueryParams(['inscripcion' => (string) $this->seasonTeam->id])
            ->actingAs($this->delegate)->test(RostersIndex::class);
    }

    /** @return array<string, string> apellido => estado */
    private function states($screen): array
    {
        return $screen->instance()->bulkCandidates()->mapWithKeys(fn ($c) => [$c->player->last_name => $c->state])->all();
    }

    public function test_candidates_show_who_fits_before_choosing(): void
    {
        $this->woman('Apaza');
        $this->woman('Joven', '2005-01-01');                                                     // 21 en 2026: menor que Libre
        Player::factory()->create(['last_name' => 'Varon', 'gender' => 'M', 'created_by' => $this->delegate->id]); // otro genero: no sale
        $already = $this->woman('Ya');
        $this->rosterEntry($this->seasonTeam, playerAttributes: [])->update(['player_id' => $already->id]);

        // De otra delegacion en esta misma disciplina: no puede.
        $otherTeam = Team::factory()->create(['name' => 'Otra']);
        $this->registerDelegation($this->edition, $otherTeam, $this->delegate);
        $clash = $this->woman('Choque');
        SeasonTeamPlayer::create(['season_team_id' => SeasonTeam::create(['season_id' => $this->volley->id, 'team_id' => $otherTeam->id, 'is_active' => true])->id,
            'player_id' => $clash->id, 'status' => 'active', 'enrolled_at' => '2026-10-20']);

        $screen = $this->screen()->call('openBulk')->assertSet('showBulk', true);

        $this->assertSame(['Apaza' => 'ok', 'Choque' => 'blocked', 'Joven' => 'warn'], $this->states($screen));

        $screen->call('selectAllEligible');
        $this->assertSame([(string) Player::where('last_name', 'Apaza')->value('id')], $screen->get('bulkSelected'));

        $this->assertSame(['Apaza'], array_keys($this->states($screen->set('bulkOnlyEligible', true))));
    }

    public function test_adds_the_valid_ones_and_reports_the_rest(): void
    {
        $a = $this->woman('Apaza');
        $b = $this->woman('Bustinza');
        $young = $this->woman('Joven', '2005-01-01');
        $c = $this->woman('Ccama');
        $d = $this->woman('Dueñas');

        // Tope 3 y reglas bloqueantes: Joven no entra por edad y la cuarta valida excede el tope.
        $this->row->update(['rules_blocking' => true]);

        $screen = $this->screen()->call('openBulk')
            ->set('bulkSelected', array_map('strval', [$a->id, $b->id, $young->id, $c->id, $d->id]))
            ->call('addSelected')
            ->assertDispatched('toast', type: 'warning');

        $result = $screen->get('bulkResult');
        $this->assertCount(3, $result['added']);
        $this->assertCount(2, $result['rejected']);
        $this->assertSame([], $screen->get('bulkSelected'));

        $entries = SeasonTeamPlayer::where('season_team_id', $this->seasonTeam->id)->get();
        $this->assertCount(3, $entries);
        $this->assertNotContains($young->id, $entries->pluck('player_id')->all());
        foreach ($entries as $entry) {
            $this->assertNull($entry->shirt_number);
            $this->assertNull($entry->position_id);
            $this->assertFalse($entry->is_captain);
        }
    }

    public function test_warnings_do_not_block_when_rules_only_warn(): void
    {
        $young = $this->woman('Joven', '2005-01-01');

        $result = app(SeasonTeamPlayerService::class)->registerMany($this->volley, $this->team, [$young->id], $this->delegate);

        $this->assertSame([$young->fullName()], $result['added']);
        $this->assertCount(1, $result['warnings']);
        $this->assertSame([], $result['rejected']);
    }

    public function test_other_delegations_and_foreign_players_are_rejected(): void
    {
        // Participante de otra delegacion: no es visible para el delegado.
        $foreign = Player::factory()->create(['gender' => 'F', 'created_by' => $this->delegate()->id]);
        $result = app(SeasonTeamPlayerService::class)->registerMany($this->volley, $this->team, [$foreign->id], $this->delegate);
        $this->assertSame([], $result['added']);
        $this->assertCount(1, $result['rejected']);

        // Plantel de una delegacion ajena.
        $other = Team::factory()->create();
        $this->registerDelegation($this->edition, $other, $this->delegate());
        $this->expectException(AuthorizationException::class);
        app(SeasonTeamPlayerService::class)->registerMany($this->volley, $other, [$this->woman('Apaza')->id], $this->delegate);
    }

    public function test_bulk_works_straight_from_planteles_choosing_discipline_and_delegation(): void
    {
        // Otra disciplina que la delegacion marco en Inscripcion por disciplina, y una que no.
        $futsal = Season::factory()->create([
            'games_edition_id' => $this->edition->id, 'gender' => 'F', 'name' => 'Futsal Libre Damas', 'status' => 'active',
        ]);
        $marked = SeasonTeam::create(['season_id' => $futsal->id, 'team_id' => $this->team->id, 'is_active' => true]);
        $unmarked = Season::factory()->create([
            'games_edition_id' => $this->edition->id, 'gender' => 'F', 'name' => 'Básquet Libre Damas', 'status' => 'active',
        ]);
        $a = $this->woman('Apaza');

        $screen = Livewire::actingAs($this->delegate)->test(RostersIndex::class)
            ->assertSee('Agregar varios')
            ->call('openBulk')
            ->assertSet('bulkSeasonId', '')
            ->assertSee('Elige la disciplina y la delegación');
        $this->assertNotContains($unmarked->id, $screen->instance()->bulkSeasonOptions()->pluck('id'));

        // Con una sola delegacion posible, se elige sola al elegir la disciplina.
        $screen->set('bulkSeasonId', (string) $futsal->id)->assertSet('bulkTeamId', (string) $this->team->id);
        $this->assertSame(['Apaza' => 'ok'], $this->states($screen));

        $screen->set('bulkSelected', [(string) $a->id])->call('addSelected')->assertDispatched('toast', type: 'success');

        // Entra al plantel de esa disciplina y la tabla pasa a ese plantel.
        $this->assertSame([$a->id], $marked->roster()->pluck('player_id')->all());
        $screen->assertSet('seasonTeamFilter', (string) $marked->id);

        // Una disciplina o delegacion ajena puesta a mano no se acepta.
        $foreignTeam = Team::factory()->create();
        $this->registerDelegation($this->edition, $foreignTeam, $this->delegate());
        $screen->set('bulkTeamId', (string) $foreignTeam->id);
        $this->assertNull($screen->instance()->bulkTeam());
        $this->assertCount(0, $screen->instance()->bulkCandidates());
    }

    public function test_rosters_screen_no_longer_offers_cards(): void
    {
        $entry = $this->rosterEntry($this->seasonTeam, playerAttributes: ['gender' => 'F']);

        $this->actingAs($this->delegate)->get(route('rosters.index', ['inscripcion' => $this->seasonTeam->id]))
            ->assertOk()
            ->assertSee($entry->player->last_name)
            ->assertDontSee('Carnet (PDF)')
            ->assertDontSee(route('cards.single', $entry), false)
            ->assertDontSee('fa-id-card', false);
    }

    public function test_filtered_roster_preloads_the_panel(): void
    {
        $this->screen()->call('openBulk')
            ->assertSet('bulkSeasonId', (string) $this->volley->id)
            ->assertSet('bulkTeamId', (string) $this->team->id);
    }

    public function test_inline_edits_shirt_position_and_captain(): void
    {
        $first = $this->rosterEntry($this->seasonTeam, playerAttributes: ['gender' => 'F']);
        $second = $this->rosterEntry($this->seasonTeam, playerAttributes: ['gender' => 'F']);
        $setter = SportPosition::create(['sport_id' => $this->volley->sport_id, 'name' => 'Armadora', 'sort_order' => 1]);
        $alien = SportPosition::create(['sport_id' => Sport::factory()->create()->id, 'name' => 'Arquero', 'sort_order' => 1]);
        $first->update(['shirt_number' => null]);
        $second->update(['shirt_number' => null]);

        $screen = $this->screen()
            ->call('updateInline', $first->id, 'shirt_number', '7')
            ->assertDispatched('toast', type: 'success');
        $this->assertSame(7, $first->refresh()->shirt_number);

        // Camiseta repetida: aviso y no se guarda.
        $screen->call('updateInline', $second->id, 'shirt_number', '7')->assertDispatched('toast', type: 'error');
        $this->assertNull($second->refresh()->shirt_number);

        $screen->call('updateInline', $first->id, 'position_id', (string) $setter->id);
        $this->assertSame($setter->id, $first->refresh()->position_id);
        $screen->call('updateInline', $first->id, 'position_id', (string) $alien->id)->assertDispatched('toast', type: 'error');
        $this->assertSame($setter->id, $first->refresh()->position_id);

        // La capitania pasa de una a otra sin pasos extra.
        $screen->call('updateInline', $first->id, 'is_captain', 1);
        $screen->call('updateInline', $second->id, 'is_captain', 1);
        $this->assertFalse($first->refresh()->is_captain);
        $this->assertTrue($second->refresh()->is_captain);

        // Campo fuera de la lista blanca.
        $screen->call('updateInline', $first->id, 'status', 'inactive')->assertDispatched('toast', type: 'error');
        $this->assertSame('active', $first->refresh()->status->value);
    }

    public function test_inline_edit_is_closed_to_other_delegations(): void
    {
        $entry = $this->rosterEntry($this->seasonTeam, playerAttributes: ['gender' => 'F']);
        $stranger = $this->delegate();
        $this->registerDelegation($this->edition, Team::factory()->create(), $stranger);

        try {
            Livewire::actingAs($stranger)->test(RostersIndex::class)->call('updateInline', $entry->id, 'shirt_number', '9')->assertNotFound();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $this->assertNotSame(9, $entry->refresh()->shirt_number);
    }
}
