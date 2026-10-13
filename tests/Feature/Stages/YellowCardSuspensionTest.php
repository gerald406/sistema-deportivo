<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Enums\SeasonTeamPlayerStatus;
use App\Livewire\GamesEditions\Index as GamesEditionsIndex;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Suspension;
use App\Services\SuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsBracket;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * F5 (Art. 14 y 15 de las bases): amarillas acumulables y sancion al
 * capitan expulsado, como datos de la olimpiada.
 */
class YellowCardSuspensionTest extends TestCase
{
    use BuildsBracket, BuildsCompetition, CreatesActors, RefreshDatabase;

    private SeasonTeam $home;

    private SeasonTeam $away;

    private SeasonTeamPlayer $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootCompetition(['yellow_cards_limit' => 2, 'captain_red_matches' => 2]);
        [$this->home, $this->away] = $this->entrants(2)->all();
        $this->player = $this->rosterEntry($this->home, 10);
    }

    /** Partido suelto (sin llave) entre las dos delegaciones. */
    private function fixture(?Season $season = null, ?SeasonTeam $home = null, ?SeasonTeam $away = null): GameMatch
    {
        $season ??= $this->season;
        $number = Matchday::where('season_id', $season->id)->count() + 1;
        $matchday = Matchday::create(['season_id' => $season->id, 'name' => "Fecha {$number}"]);
        $match = GameMatch::create(['season_id' => $season->id, 'matchday_id' => $matchday->id, 'round_type' => 'regular', 'status' => 'pending']);

        foreach (['home' => $home ?? $this->home, 'away' => $away ?? $this->away] as $side => $seasonTeam) {
            EventParticipant::create(['match_id' => $match->id, 'participant_type' => 'season_team', 'participant_id' => $seasonTeam->id, 'side' => $side]);
        }

        return $match;
    }

    /** Juega un partido en el que $entry recibe la tarjeta $code. */
    private function playWithCard(SeasonTeamPlayer $entry, string $code = 'yellow_card', ?GameMatch $match = null): GameMatch
    {
        $match ??= $this->fixture();
        $this->cardEvent($match, $entry, $code);
        $this->close($match, 1, 0);

        return $match;
    }

    private function accumulationSuspensions(?SeasonTeamPlayer $entry = null)
    {
        return Suspension::where('season_team_player_id', ($entry ?? $this->player)->id)
            ->where('reason', SuspensionService::YELLOW_ACCUMULATION_REASON)->get();
    }

    public function test_second_yellow_in_the_competition_suspends_one_match(): void
    {
        $this->playWithCard($this->player);
        $second = $this->playWithCard($this->player);

        $suspension = $this->accumulationSuspensions()->sole();
        $this->assertSame(1, $suspension->matches_suspended);
        $this->assertSame($second->id, $suspension->match_id);
        $this->assertFalse($suspension->is_served);
        $this->assertSame(SeasonTeamPlayerStatus::Suspended, $this->player->fresh()->status);
    }

    public function test_first_yellow_does_not_suspend(): void
    {
        $this->playWithCard($this->player);

        $this->assertSame(0, Suspension::count());
        $this->assertSame(SeasonTeamPlayerStatus::Active, $this->player->fresh()->status);
    }

    public function test_fourth_yellow_suspends_again(): void
    {
        foreach (range(1, 3) as $i) {
            $this->playWithCard($this->player);
        }
        $this->assertCount(1, $this->accumulationSuspensions());

        $this->playWithCard($this->player);

        $this->assertCount(2, $this->accumulationSuspensions());
        $this->assertSame(2, $this->accumulationSuspensions()->pluck('match_id')->unique()->count());
    }

    public function test_no_accumulation_when_limit_is_empty(): void
    {
        $this->edition->update(['yellow_cards_limit' => null]);

        foreach (range(1, 4) as $i) {
            $this->playWithCard($this->player);
        }

        $this->assertSame(0, Suspension::count());
    }

    public function test_yellows_from_another_competition_do_not_count(): void
    {
        $this->playWithCard($this->player);

        // El mismo participante, en futsal: otra competencia, otra ficha de plantel.
        $futsal = $this->headToHeadSeason();
        [$home, $away] = $this->entrants(2, $futsal)->all();
        $sameParticipant = SeasonTeamPlayer::create([
            'season_team_id' => $home->id, 'player_id' => $this->player->player_id, 'status' => 'active', 'enrolled_at' => '2026-10-20',
        ]);
        $this->playWithCard($sameParticipant, match: $this->fixture($futsal, $home, $away));

        $this->assertSame(0, Suspension::count());
    }

    public function test_reclosing_a_match_does_not_duplicate(): void
    {
        $first = $this->playWithCard($this->player);
        $second = $this->playWithCard($this->player);

        // Reabrir y volver a cerrar el partido de la segunda amarilla.
        $this->reopen($second);
        $this->assertCount(0, $this->accumulationSuspensions());
        $this->assertSame(SeasonTeamPlayerStatus::Active, $this->player->fresh()->status);
        $this->close($second, 1, 0);
        $this->assertCount(1, $this->accumulationSuspensions());

        // Reabrir y volver a cerrar el de la PRIMERA tampoco crea otra.
        $this->reopen($first);
        $this->close($first, 2, 0);
        $this->assertCount(1, $this->accumulationSuspensions());
    }

    public function test_two_participants_are_counted_separately(): void
    {
        $other = $this->rosterEntry($this->away, 7);

        $match = $this->fixture();
        $this->cardEvent($match, $this->player, 'yellow_card');
        $this->cardEvent($match, $other, 'yellow_card');
        $this->close($match, 1, 0);
        $this->playWithCard($other);

        $this->assertCount(0, $this->accumulationSuspensions());
        $this->assertCount(1, $this->accumulationSuspensions($other));
    }

    public function test_captain_red_card_uses_configured_matches(): void
    {
        $captain = $this->rosterEntry($this->home, 5);
        $captain->update(['is_captain' => true]);

        $match = $this->fixture();
        $this->cardEvent($match, $captain, 'red_card');
        $this->cardEvent($match, $this->player, 'red_card');
        $this->close($match, 0, 1);

        $this->assertSame(2, Suspension::where('season_team_player_id', $captain->id)->sole()->matches_suspended);
        $this->assertSame(1, Suspension::where('season_team_player_id', $this->player->id)->sole()->matches_suspended);
    }

    public function test_red_card_default_is_one_match_for_everyone(): void
    {
        $this->edition->update(['captain_red_matches' => null]);
        $captain = $this->rosterEntry($this->home, 5);
        $captain->update(['is_captain' => true]);

        $match = $this->fixture();
        $this->cardEvent($match, $captain, 'red_card_indirect');
        $this->cardEvent($match, $this->player, 'red_card');
        $this->close($match, 0, 1);

        $this->assertSame([1, 1], Suspension::orderBy('id')->pluck('matches_suspended')->all());
    }

    public function test_rules_are_edited_on_the_edition_form(): void
    {
        Livewire::actingAs($this->organizer)->test(GamesEditionsIndex::class)
            ->call('edit', $this->edition->id)
            ->assertSet('form.yellow_cards_limit', 2)
            ->set('form.yellow_cards_limit', 3)
            ->set('form.captain_red_matches', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->edition->refresh();
        $this->assertSame(3, $this->edition->yellow_cards_limit);
        $this->assertNull($this->edition->captain_red_matches);

        Livewire::actingAs($this->organizer)->test(GamesEditionsIndex::class)
            ->call('edit', $this->edition->id)
            ->set('form.yellow_cards_limit', 0)
            ->call('save')
            ->assertHasErrors('form.yellow_cards_limit');
    }
}
