<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeasonTeamPlayerStatus;
use App\Models\GamesEdition;
use App\Models\GamesEditionTeam;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonTeamPlayer;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Padron de participantes de una olimpiada (solo lectura): por delegacion
 * (una fila por persona, con todas sus disciplinas) y por disciplina
 * (deporte y, opcionalmente, una competencia).
 *
 * Solo quien gestiona la olimpiada (GamesEditionService::manages()): se
 * revalida aqui en cada metodo publico, porque los filtros vienen del
 * navegador. Cuenta lo mismo que el Seguimiento de delegaciones:
 * inscripciones activas en competencias activas y participantes que no
 * estan de baja.
 */
class ParticipantRosterService
{
    private const PLAYER_FIELDS = ['id', 'dni', 'first_name', 'last_name', 'birth_date', 'gender', 'staff_type', 'employment_type', 'documents_verified_at', 'resolution_path'];

    public function __construct(
        private GamesEditionService $editions,
        private RosterRuleService $rules,
    ) {
    }

    /** Delegaciones inscritas en la olimpiada, para el filtro. */
    public function delegationOptions(User $actor, GamesEdition $edition): Collection
    {
        $this->ensureManages($actor, $edition);

        return GamesEditionTeam::where('games_edition_id', $edition->id)
            ->join('teams', 'teams.id', '=', 'games_edition_teams.team_id')
            ->orderBy('teams.name')
            ->get(['teams.id', 'teams.name']);
    }

    /** Deportes con alguna competencia activa en la olimpiada. */
    public function sportOptions(User $actor, GamesEdition $edition): Collection
    {
        $this->ensureManages($actor, $edition);

        return Sport::whereHas('seasons', fn ($q) => $q->where('games_edition_id', $edition->id)->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Competencias activas del deporte en la olimpiada. */
    public function seasonOptions(User $actor, GamesEdition $edition, ?int $sportId): Collection
    {
        $this->ensureManages($actor, $edition);

        return $sportId === null
            ? collect()
            : Season::where('games_edition_id', $edition->id)->where('sport_id', $sportId)->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
    }

    /**
     * Una fila por persona y delegacion, ordenadas por delegacion y apellidos.
     *
     * @return Collection<int, object{team: Team, player: Player, age: ?int, disciplines: Collection<int, string>}>
     */
    public function byDelegation(User $actor, GamesEdition $edition, ?int $teamId = null, string $search = ''): Collection
    {
        $this->ensureManages($actor, $edition);
        $year = $this->rules->editionYear($edition);

        // Filas planas (sin hidratar modelos): una persona en 20 disciplinas son 20 filas.
        $rows = $this->entries($edition, $teamId, $search)
            ->select('season_team.team_id', 'season_team_player.player_id', 'seasons.name as discipline')
            ->orderBy('teams.name')->orderBy('players.last_name')->orderBy('players.first_name')->orderBy('seasons.name')
            ->toBase()
            ->get();

        $players = Player::whereKey($rows->pluck('player_id')->unique())->get(self::PLAYER_FIELDS)->keyBy('id');
        $teams = Team::whereKey($rows->pluck('team_id')->unique())->get(['id', 'name'])->keyBy('id');

        return $rows
            ->groupBy(fn ($r) => $r->team_id.'-'.$r->player_id)
            ->map(function (Collection $entries) use ($players, $teams, $year) {
                $player = $players[$entries->first()->player_id];

                return (object) [
                    'team' => $teams[$entries->first()->team_id],
                    'player' => $player,
                    'age' => $player->birth_date ? $year - $player->birth_date->year : null,
                    'disciplines' => $entries->pluck('discipline')->unique()->values(),
                ];
            })
            ->values();
    }

    /**
     * Filas de plantel ordenadas por competencia, delegacion y apellidos;
     * cada una con su edad en ->age.
     *
     * @return Collection<int, SeasonTeamPlayer>
     */
    public function byDiscipline(User $actor, GamesEdition $edition, ?int $sportId = null, ?int $seasonId = null, ?int $teamId = null, string $search = ''): Collection
    {
        $this->ensureManages($actor, $edition);
        $year = $this->rules->editionYear($edition);

        return $this->entries($edition, $teamId, $search)
            ->when($sportId !== null, fn ($q) => $q->where('seasons.sport_id', $sportId))
            ->when($seasonId !== null, fn ($q) => $q->where('seasons.id', $seasonId))
            ->orderBy('seasons.name')->orderBy('teams.name')
            ->orderByRaw('season_team_player.shirt_number IS NULL')->orderBy('season_team_player.shirt_number')
            ->orderBy('players.last_name')->orderBy('players.first_name')
            ->select('season_team_player.*')
            ->with(['player:'.implode(',', self::PLAYER_FIELDS), 'seasonTeam:id,season_id,team_id', 'seasonTeam.team:id,name', 'seasonTeam.season:id,name,sport_id'])
            ->get()
            ->each(fn (SeasonTeamPlayer $e) => $e->age = $e->player->birth_date ? $year - $e->player->birth_date->year : null);
    }

    private function ensureManages(User $actor, GamesEdition $edition): void
    {
        if (! $this->editions->manages($actor, $edition)) {
            throw new AuthorizationException('Solo quien gestiona la olimpiada ve su padrón.');
        }
    }

    /** Filas de plantel vigentes de la olimpiada (con joins a delegacion, competencia y persona); cada reporte elige sus columnas. */
    private function entries(GamesEdition $edition, ?int $teamId, string $search): Builder
    {
        $search = trim($search);

        return SeasonTeamPlayer::query()
            ->join('season_team', 'season_team.id', '=', 'season_team_player.season_team_id')
            ->join('seasons', 'seasons.id', '=', 'season_team.season_id')
            ->join('teams', 'teams.id', '=', 'season_team.team_id')
            ->join('players', 'players.id', '=', 'season_team_player.player_id')
            ->where('seasons.games_edition_id', $edition->id)
            ->where('seasons.is_active', true)
            ->where('season_team.is_active', true)
            ->where('season_team_player.status', '!=', SeasonTeamPlayerStatus::Inactive->value)
            ->when($teamId !== null, fn ($q) => $q->where('season_team.team_id', $teamId))
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('players.dni', 'like', "%{$search}%")
                ->orWhereRaw("CONCAT(players.last_name, ' ', players.first_name) LIKE ?", ["%{$search}%"])
                ->orWhereRaw("CONCAT(players.first_name, ' ', players.last_name) LIKE ?", ["%{$search}%"])));
    }
}
