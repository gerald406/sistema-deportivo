<?php

declare(strict_types=1);

namespace Tests\Feature\Editions;

use App\Livewire\Reports\DelegationRoster;
use App\Livewire\Reports\DisciplineRoster;
use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Services\ParticipantRosterService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/** Padron por delegacion y por disciplina: solo quien gestiona la olimpiada. */
class ParticipantRosterTest extends TestCase
{
    use BuildsCompetition, CreatesActors, RefreshDatabase;

    private User $organizer;

    private GamesEdition $edition;

    private Team $team;

    private Team $other;

    private Sport $footballSport;

    private Season $footballMen;

    private Season $footballWomen;

    private Season $chess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = $this->organizer();
        $this->edition = GamesEdition::factory()->create(['organizer_id' => $this->organizer->id, 'start_date' => '2026-10-28']);
        $this->team = Team::factory()->create(['name' => 'IESP Yunguyo']);
        $this->other = Team::factory()->create(['name' => 'ESFA Puno']);
        $this->registerDelegation($this->edition, $this->team, $this->delegate());
        $this->registerDelegation($this->edition, $this->other, $this->delegate());

        $this->footballSport = Sport::factory()->create(['name' => 'Fútbol']);
        $this->footballMen = $this->season('Fútbol Libre Varones', $this->footballSport);
        $this->footballWomen = $this->season('Fútbol Libre Damas', $this->footballSport);
        $this->chess = $this->season('Ajedrez Libre Varones', Sport::factory()->create(['name' => 'Ajedrez']));
    }

    private function season(string $name, Sport $sport): Season
    {
        return Season::factory()->create([
            'games_edition_id' => $this->edition->id, 'sport_id' => $sport->id,
            'name' => $name, 'is_active' => true,
        ]);
    }

    private function enroll(Season $season, Team $team): SeasonTeam
    {
        return SeasonTeam::create(['season_id' => $season->id, 'team_id' => $team->id, 'is_active' => true]);
    }

    private function alsoIn(SeasonTeam $seasonTeam, SeasonTeamPlayer $entry): SeasonTeamPlayer
    {
        return SeasonTeamPlayer::create(['season_team_id' => $seasonTeam->id, 'player_id' => $entry->player_id, 'status' => 'active', 'enrolled_at' => '2026-10-20']);
    }

    private function service(): ParticipantRosterService
    {
        return app(ParticipantRosterService::class);
    }

    public function test_delegation_roster_lists_each_person_once_with_all_their_disciplines(): void
    {
        $football = $this->enroll($this->footballMen, $this->team);
        $chess = $this->enroll($this->chess, $this->team);

        $entry = $this->rosterEntry($football, 9, ['last_name' => 'Quispe', 'birth_date' => '1990-05-01']);
        $this->alsoIn($chess, $entry);
        $this->rosterEntry($football, 10, ['last_name' => 'Apaza']);
        // De baja: no sale.
        $this->rosterEntry($football, 11, ['last_name' => 'Baja'])->update(['status' => 'inactive']);
        // Inscripcion desactivada: no sale.
        $this->rosterEntry($this->enroll($this->footballWomen, $this->team), null, ['last_name' => 'Desactivada'])
            ->seasonTeam->update(['is_active' => false]);

        $rows = $this->service()->byDelegation($this->organizer, $this->edition);

        $this->assertSame(['Apaza', 'Quispe'], $rows->pluck('player.last_name')->all());

        $quispe = $rows->firstWhere('player.last_name', 'Quispe');
        $this->assertSame(['Ajedrez Libre Varones', 'Fútbol Libre Varones'], $quispe->disciplines->all());
        $this->assertSame(36, $quispe->age);
        $this->assertSame('IESP Yunguyo', $quispe->team->name);
    }

    public function test_delegation_roster_filters_by_delegation_and_search(): void
    {
        $this->rosterEntry($this->enroll($this->footballMen, $this->team), 9, ['last_name' => 'Quispe', 'dni' => '45531234']);
        $this->rosterEntry($this->enroll($this->footballMen, $this->other), 9, ['last_name' => 'Mamani', 'dni' => '40001111']);

        $this->assertSame(['ESFA Puno', 'IESP Yunguyo'], $this->service()->byDelegation($this->organizer, $this->edition)->pluck('team.name')->all());
        $this->assertSame(['Mamani'], $this->service()->byDelegation($this->organizer, $this->edition, $this->other->id)->pluck('player.last_name')->all());
        $this->assertSame(['Quispe'], $this->service()->byDelegation($this->organizer, $this->edition, null, '4553')->pluck('player.last_name')->all());
        $this->assertSame(['Mamani'], $this->service()->byDelegation($this->organizer, $this->edition, null, 'mama')->pluck('player.last_name')->all());
    }

    public function test_discipline_roster_filters_by_sport_and_competition(): void
    {
        $this->rosterEntry($this->enroll($this->footballMen, $this->team), 9, ['last_name' => 'Quispe']);
        $this->rosterEntry($this->enroll($this->footballWomen, $this->other), 5, ['last_name' => 'Condori']);
        $this->rosterEntry($this->enroll($this->chess, $this->team), null, ['last_name' => 'Ticona']);

        $bySport = $this->service()->byDiscipline($this->organizer, $this->edition, $this->footballSport->id);
        $this->assertSame(['Fútbol Libre Damas', 'Fútbol Libre Varones'], $bySport->pluck('seasonTeam.season.name')->all());

        $bySeason = $this->service()->byDiscipline($this->organizer, $this->edition, $this->footballSport->id, $this->footballMen->id);
        $this->assertSame(['Quispe'], $bySeason->pluck('player.last_name')->all());

        // Una competencia de otro deporte no se cuela por el filtro de competencia.
        $this->assertCount(0, $this->service()->byDiscipline($this->organizer, $this->edition, $this->footballSport->id, $this->chess->id));

        $this->assertCount(3, $this->service()->byDiscipline($this->organizer, $this->edition));
        $this->assertSame(['Condori'], $this->service()->byDiscipline($this->organizer, $this->edition, null, null, $this->other->id)->pluck('player.last_name')->all());
    }

    public function test_only_who_manages_the_edition_can_read_the_rosters(): void
    {
        $this->rosterEntry($this->enroll($this->footballMen, $this->team), 9);

        $this->assertCount(1, $this->service()->byDelegation($this->admin(), $this->edition));

        $this->expectException(AuthorizationException::class);
        $this->service()->byDiscipline($this->organizer(), $this->edition);
    }

    public function test_screens_reject_delegates_and_show_the_resolution_button(): void
    {
        $football = $this->enroll($this->footballMen, $this->team);
        $this->rosterEntry($football, 9, ['last_name' => 'Quispe'])->player->forceFill(['resolution_path' => 'resolutions/x.pdf'])->save();
        $this->rosterEntry($football, 10, ['last_name' => 'Apaza']);

        Livewire::actingAs($this->delegate())->test(DelegationRoster::class)->assertForbidden();
        Livewire::actingAs($this->delegate())->test(DisciplineRoster::class)->assertForbidden();

        foreach ([DelegationRoster::class => [], DisciplineRoster::class => ['sportId' => (string) $this->footballSport->id]] as $screen => $filters) {
            Livewire::actingAs($this->organizer)->test($screen)
                ->set($filters)
                ->assertSet('editionId', (string) $this->edition->id)
                ->assertSee('Quispe')
                ->assertSee('Apaza')
                ->assertSeeHtml('data-url="'.route('players.resolution', SeasonTeamPlayer::first()->player_id).'"')
                ->assertSee('Sin resolución');
        }

        // Otro organizador no ve la olimpiada ajena.
        Livewire::actingAs($this->organizer())->test(DelegationRoster::class)
            ->assertSet('editionId', '')
            ->assertDontSee('Quispe');
    }

    public function test_discipline_screen_waits_for_a_sport_but_excel_brings_every_discipline(): void
    {
        $this->rosterEntry($this->enroll($this->footballMen, $this->team), 9, ['last_name' => 'Quispe']);
        $this->rosterEntry($this->enroll($this->chess, $this->team), null, ['last_name' => 'Ticona']);

        Livewire::actingAs($this->organizer)->test(DisciplineRoster::class)
            ->assertSee('Elige un deporte para ver su padrón.')
            ->assertDontSee('Quispe')
            ->call('export')
            ->assertFileDownloaded();

        // Un id de deporte ajeno a la olimpiada no cuenta como elegido.
        Livewire::actingAs($this->organizer)->test(DisciplineRoster::class)
            ->set('sportId', (string) Sport::factory()->create()->id)
            ->assertSee('Elige un deporte para ver su padrón.');
    }

    public function test_competition_filter_resets_when_the_sport_changes(): void
    {
        Livewire::actingAs($this->organizer)->test(DisciplineRoster::class)
            ->set('sportId', (string) $this->footballSport->id)
            ->set('seasonId', (string) $this->footballMen->id)
            ->set('sportId', '')
            ->assertSet('seasonId', '');
    }

    public function test_routes_render_for_the_organizer(): void
    {
        $this->actingAs($this->organizer)->get(route('reports.delegation-roster'))->assertOk();
        $this->actingAs($this->organizer)->get(route('reports.discipline-roster'))->assertOk();
    }
}
