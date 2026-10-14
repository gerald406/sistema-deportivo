<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Enums\SeasonTeamPlayerStatus;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Suspension;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBracket;
use Tests\Concerns\BuildsCompetition;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * Sanciones automaticas: toda roja da 1 partido, sea o no capitan el
 * expulsado. La acumulacion de amarillas y la sancion especial al
 * capitan se manejan fuera del sistema (decision del cliente).
 */
class RedCardSuspensionTest extends TestCase
{
    use BuildsBracket, BuildsCompetition, CreatesActors, RefreshDatabase;

    private SeasonTeam $home;

    private SeasonTeam $away;

    private SeasonTeamPlayer $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootCompetition();
        [$this->home, $this->away] = $this->entrants(2)->all();
        $this->player = $this->rosterEntry($this->home, 10);
    }

    /** Partido suelto (sin llave) entre las dos delegaciones. */
    private function fixture(): GameMatch
    {
        $number = Matchday::where('season_id', $this->season->id)->count() + 1;
        $matchday = Matchday::create(['season_id' => $this->season->id, 'name' => "Fecha {$number}"]);
        $match = GameMatch::create(['season_id' => $this->season->id, 'matchday_id' => $matchday->id, 'round_type' => 'regular', 'status' => 'pending']);

        foreach (['home' => $this->home, 'away' => $this->away] as $side => $seasonTeam) {
            EventParticipant::create(['match_id' => $match->id, 'participant_type' => 'season_team', 'participant_id' => $seasonTeam->id, 'side' => $side]);
        }

        return $match;
    }

    public function test_red_card_is_one_match_for_everyone_including_the_captain(): void
    {
        $captain = $this->rosterEntry($this->home, 5);
        $captain->update(['is_captain' => true]);

        $match = $this->fixture();
        $this->cardEvent($match, $captain, 'red_card');
        $this->cardEvent($match, $this->player, 'red_card_indirect');
        $this->close($match, 0, 1);

        $this->assertSame(1, Suspension::where('season_team_player_id', $captain->id)->sole()->matches_suspended);
        $this->assertSame(1, Suspension::where('season_team_player_id', $this->player->id)->sole()->matches_suspended);
        $this->assertSame(SeasonTeamPlayerStatus::Suspended, $captain->fresh()->status);
    }

    public function test_yellow_cards_never_suspend_automatically(): void
    {
        foreach (range(1, 4) as $i) {
            $match = $this->fixture();
            $this->cardEvent($match, $this->player, 'yellow_card');
            $this->close($match, 1, 0);
        }

        $this->assertSame(0, Suspension::count());
        $this->assertSame(SeasonTeamPlayerStatus::Active, $this->player->fresh()->status);
    }

    public function test_reclosing_a_match_does_not_duplicate(): void
    {
        $match = $this->fixture();
        $this->cardEvent($match, $this->player, 'red_card');
        $this->close($match, 1, 0);

        $this->reopen($match);
        $this->close($match, 1, 0);

        $this->assertSame(1, Suspension::where('season_team_player_id', $this->player->id)->count());
    }
}
