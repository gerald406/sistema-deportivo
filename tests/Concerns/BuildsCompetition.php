<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\ScoringConfig;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;

/**
 * Conjunto minimo de datos de una competencia en juego: olimpiada con su
 * organizador, una competencia de enfrentamiento, dos delegaciones con un
 * participante cada una y un partido pendiente entre ambas. Lo usan la
 * prueba de humo (para que las vistas rendericen filas reales) y las
 * pruebas de alcance por rol.
 */
trait BuildsCompetition
{
    /**
     * @return object{edition: GamesEdition, organizer: User, season: Season, homeTeam: Team, awayTeam: Team, home: SeasonTeam, away: SeasonTeam, homeEntry: SeasonTeamPlayer, awayEntry: SeasonTeamPlayer, matchday: Matchday, match: GameMatch}
     */
    protected function buildCompetition(?User $organizer = null): object
    {
        $organizer ??= $this->organizer();

        $edition = GamesEdition::factory()->create(['organizer_id' => $organizer->id]);
        $season = Season::factory()->create([
            'games_edition_id' => $edition->id,
            'sport_id' => Sport::factory()->create()->id,
            'start_date' => '2026-10-28',
        ]);
        ScoringConfig::create(['season_id' => $season->id, 'phase_name' => 'Fase única']);

        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $home = SeasonTeam::create(['season_id' => $season->id, 'team_id' => $homeTeam->id, 'is_active' => true]);
        $away = SeasonTeam::create(['season_id' => $season->id, 'team_id' => $awayTeam->id, 'is_active' => true]);

        $homeEntry = $this->rosterEntry($home, 10);
        $awayEntry = $this->rosterEntry($away, 7);

        $matchday = Matchday::create(['season_id' => $season->id, 'name' => 'Fecha 1']);
        $match = GameMatch::create([
            'season_id' => $season->id,
            'matchday_id' => $matchday->id,
            'round_type' => 'regular',
            'status' => 'pending',
            'scheduled_at' => '2026-10-28 09:00:00',
        ]);

        foreach (['home' => $home, 'away' => $away] as $side => $seasonTeam) {
            EventParticipant::create([
                'match_id' => $match->id,
                'participant_type' => 'season_team',
                'participant_id' => $seasonTeam->id,
                'side' => $side,
            ]);
        }

        return (object) compact(
            'edition', 'organizer', 'season', 'homeTeam', 'awayTeam', 'home', 'away',
            'homeEntry', 'awayEntry', 'matchday', 'match',
        );
    }

    protected function rosterEntry(SeasonTeam $seasonTeam, ?int $shirtNumber = null, array $playerAttributes = []): SeasonTeamPlayer
    {
        return SeasonTeamPlayer::create([
            'season_team_id' => $seasonTeam->id,
            'player_id' => Player::factory()->create($playerAttributes)->id,
            'shirt_number' => $shirtNumber,
            'status' => 'active',
            'enrolled_at' => '2026-10-20',
        ]);
    }
}
