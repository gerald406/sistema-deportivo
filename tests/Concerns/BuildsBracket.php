<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\StageMode;
use App\Models\EventType;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\MatchEvent;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Services\BracketService;
use App\Services\ResultService;
use Illuminate\Support\Collection;

/**
 * Competencia de enfrentamiento con una fase de partidos y N delegaciones
 * inscritas, para las pruebas de llaves, desempates y sanciones.
 * Requiere CreatesActors y BuildsCompetition.
 */
trait BuildsBracket
{
    protected User $organizer;

    protected GamesEdition $edition;

    protected Season $season;

    protected function bootCompetition(array $editionAttributes = []): void
    {
        $this->organizer = $this->organizer();
        $this->edition = GamesEdition::factory()->create($editionAttributes + ['organizer_id' => $this->organizer->id]);
        $this->season = $this->headToHeadSeason();
    }

    protected function headToHeadSeason(?Sport $sport = null): Season
    {
        return Season::factory()->create([
            'games_edition_id' => $this->edition->id,
            'sport_id' => ($sport ?? Sport::factory()->create(['format_type' => 'head_to_head']))->id,
        ]);
    }

    protected function stage(array $attributes = [], ?Season $season = null): SeasonStage
    {
        return SeasonStage::factory()->create($attributes + [
            'season_id' => ($season ?? $this->season)->id,
            'mode' => StageMode::Matches,
        ]);
    }

    /** @return Collection<int, SeasonTeam> N delegaciones inscritas en la competencia, en orden */
    protected function entrants(int $count, ?Season $season = null): Collection
    {
        $season ??= $this->season;

        return collect(range(1, $count))->map(function () use ($season) {
            $team = Team::factory()->create();
            $this->registerDelegation($this->edition, $team);

            return SeasonTeam::create(['season_id' => $season->id, 'team_id' => $team->id, 'is_active' => true]);
        });
    }

    /** @return Collection<int, GameMatch> */
    protected function bracket(SeasonStage $stage, Collection $entrants): Collection
    {
        return app(BracketService::class)->generate($stage, $entrants->pluck('id')->all(), $this->organizer);
    }

    protected function close(GameMatch $match, int $home, int $away, array $extra = []): void
    {
        app(ResultService::class)->close($match->fresh(), ['home' => $home, 'away' => $away] + $extra, $this->organizer);
    }

    protected function reopen(GameMatch $match): void
    {
        app(ResultService::class)->reopen($match->fresh(), 'Error al digitar el marcador', $this->organizer);
    }

    /** @return array{home: ?int, away: ?int} season_team ids sentados en el partido */
    protected function seats(GameMatch $match): array
    {
        $participants = $match->participants()->get();

        return [
            'home' => $participants->firstWhere('side.value', 'home')?->participant_id,
            'away' => $participants->firstWhere('side.value', 'away')?->participant_id,
        ];
    }

    protected function cardEvent(GameMatch $match, SeasonTeamPlayer $entry, string $code): MatchEvent
    {
        $names = ['yellow_card' => 'Tarjeta amarilla', 'red_card' => 'Tarjeta roja directa', 'red_card_indirect' => 'Tarjeta roja por doble amarilla', 'goal' => 'Gol'];
        $type = EventType::firstOrCreate(['sport_id' => $match->season->sport_id, 'code' => $code], ['name' => $names[$code]]);

        return MatchEvent::create(['match_id' => $match->id, 'season_team_player_id' => $entry->id, 'event_type_id' => $type->id, 'minute' => 10]);
    }
}
