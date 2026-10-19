<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Gender;
use App\Enums\SeasonTeamPlayerStatus;
use App\Models\GamesEdition;
use App\Models\GamesEditionTeam;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seguimiento de delegaciones (solo lectura): cuantas personas registro
 * cada delegacion, en cuantas disciplinas participa y, en detalle, el
 * plantel de cada disciplina para cotejarlo con su ficha general.
 *
 * Alcance: quien gestiona la olimpiada ve todas sus delegaciones; el
 * delegado (titular o suplente) solo las suyas. Se revalida aqui, no solo en
 * la pantalla: teamId y editionId vienen del navegador.
 *
 * Cuenta solo inscripciones activas en competencias activas y participantes
 * que no estan de baja (SeasonTeamPlayerStatus::Inactive), igual que el
 * tablero de Inscripcion por disciplina.
 */
class DelegationTrackingService
{
    public function __construct(
        private GamesEditionService $editions,
        private DelegationEnrollmentService $enrollment,
        private RosterRuleService $rules,
    ) {
    }

    /** Olimpiadas que $actor puede seguir: las que gestiona y aquellas donde es delegado. */
    public function editionsFor(User $actor): Collection
    {
        return $this->enrollment->editionsFor($actor);
    }

    /** Inscripciones de delegaciones en la olimpiada que $actor ve: todas si la gestiona; si no, las suyas. */
    public function entriesFor(User $actor, GamesEdition $edition): Builder
    {
        $manages = $this->editions->manages($actor, $edition);

        return GamesEditionTeam::query()
            ->where('games_edition_teams.games_edition_id', $edition->id)
            ->unless($manages, fn ($q) => $actor->hasRole('delegado')
                ? $q->where(fn ($d) => $d->where('delegate_id', $actor->id)->orWhere('substitute_delegate_id', $actor->id))
                : $q->whereRaw('1 = 0'));
    }

    public function canView(User $actor, GamesEdition $edition, Team $team): bool
    {
        return $this->entriesFor($actor, $edition)->where('team_id', $team->id)->exists();
    }

    /**
     * Una fila por delegacion visible, con sus totales.
     *
     * @return Collection<int, object{entry: GamesEditionTeam, disciplines: int, withRoster: int, empty: int, people: int, men: int, women: int, verified: int}>
     */
    public function summary(User $actor, GamesEdition $edition, string $search = '', ?int $groupId = null): Collection
    {
        $entries = $this->entriesFor($actor, $edition)
            ->join('teams', 'teams.id', '=', 'games_edition_teams.team_id')
            ->leftJoin('games_edition_groups', 'games_edition_groups.id', '=', 'games_edition_teams.group_id')
            ->select('games_edition_teams.*')
            ->when($search !== '', fn ($q) => $q->where('teams.name', 'like', "%{$search}%"))
            ->when($groupId !== null, fn ($q) => $q->where('games_edition_teams.group_id', $groupId))
            ->with(['team:id,name', 'group:id,name', 'delegate:id,name', 'substituteDelegate:id,name'])
            ->orderBy('games_edition_groups.sort_order')
            ->orderBy('teams.name')
            ->get();

        $teamIds = $entries->pluck('team_id');
        $disciplines = $this->disciplineCounts($edition, $teamIds);
        $people = $this->peopleCounts($edition, $teamIds);

        return $entries->map(function (GamesEditionTeam $entry) use ($disciplines, $people) {
            $d = $disciplines->get($entry->team_id);
            $p = $people->get($entry->team_id);

            return (object) [
                'entry' => $entry,
                'disciplines' => (int) ($d->disciplines ?? 0),
                'withRoster' => (int) ($d->with_roster ?? 0),
                'empty' => (int) ($d->disciplines ?? 0) - (int) ($d->with_roster ?? 0),
                'people' => (int) ($p->people ?? 0),
                'men' => (int) ($p->men ?? 0),
                'women' => (int) ($p->women ?? 0),
                'verified' => (int) ($p->verified ?? 0),
            ];
        });
    }

    /**
     * Disciplinas en las que participa la delegacion, cada una con su
     * plantel y sus topes, para cotejarla con la ficha general.
     *
     * @return Collection<int, object{seasonTeam: SeasonTeam, players: Collection, count: int, min: ?int, max: ?int, kind: string}>
     */
    public function detail(GamesEdition $edition, Team $team): Collection
    {
        $year = null;

        return $this->activeSeasonTeams($edition)
            ->where('season_team.team_id', $team->id)
            ->select('season_team.*')
            ->with([
                'season',
                'season.sport:id,name,format_type', 'season.discipline:id,name,format_type',
                'season.category:id,name,min_age', 'season.event:id,min_players,max_players',
                'season.gamesEdition:id,start_date',
                'roster' => fn ($q) => $q->where('status', '!=', SeasonTeamPlayerStatus::Inactive->value)
                    ->with('player:id,dni,first_name,last_name,birth_date,gender,documents_verified_at'),
            ])
            ->orderBy('seasons.name')
            ->get()
            ->map(function (SeasonTeam $seasonTeam) use (&$year) {
                $season = $seasonTeam->season;
                $year ??= $this->rules->referenceYear($season);
                $players = $seasonTeam->roster
                    ->sortBy(fn ($e) => [$e->shirt_number === null, $e->shirt_number, $e->player->last_name, $e->player->first_name])
                    ->values()
                    ->each(fn ($e) => $e->age = $e->player->birth_date ? $year - $e->player->birth_date->year : null);

                return (object) [
                    'seasonTeam' => $seasonTeam,
                    'players' => $players,
                    'count' => $players->count(),
                    'min' => $season->event?->min_players,
                    'max' => $season->event?->max_players,
                    'kind' => $this->enrollment->kindOf($season),
                ];
            });
    }

    /** Inscripciones activas en competencias activas de la olimpiada. */
    private function activeSeasonTeams(GamesEdition $edition): Builder
    {
        return SeasonTeam::query()
            ->join('seasons', 'seasons.id', '=', 'season_team.season_id')
            ->where('seasons.games_edition_id', $edition->id)
            ->where('seasons.is_active', true)
            ->where('season_team.is_active', true);
    }

    /** @return Collection<int, object{disciplines: int, with_roster: int}> por team_id */
    private function disciplineCounts(GamesEdition $edition, Collection $teamIds): Collection
    {
        $withRoster = DB::table('season_team_player')
            ->whereColumn('season_team_player.season_team_id', 'season_team.id')
            ->where('season_team_player.status', '!=', SeasonTeamPlayerStatus::Inactive->value);

        return $this->activeSeasonTeams($edition)
            ->whereIn('season_team.team_id', $teamIds)
            ->groupBy('season_team.team_id')
            ->selectRaw('season_team.team_id, COUNT(*) as disciplines')
            ->selectRaw('SUM(CASE WHEN EXISTS ('.$withRoster->toSql().') THEN 1 ELSE 0 END) as with_roster', $withRoster->getBindings())
            ->toBase()
            ->get()
            ->keyBy('team_id');
    }

    /** Personas distintas (no las filas de plantel): una persona en 5 disciplinas cuenta 1. @return Collection<int, object> por team_id */
    private function peopleCounts(GamesEdition $edition, Collection $teamIds): Collection
    {
        return $this->activeSeasonTeams($edition)
            ->join('season_team_player', 'season_team_player.season_team_id', '=', 'season_team.id')
            ->join('players', 'players.id', '=', 'season_team_player.player_id')
            ->whereIn('season_team.team_id', $teamIds)
            ->where('season_team_player.status', '!=', SeasonTeamPlayerStatus::Inactive->value)
            ->groupBy('season_team.team_id')
            ->selectRaw('season_team.team_id, COUNT(DISTINCT players.id) as people')
            ->selectRaw('COUNT(DISTINCT CASE WHEN players.gender = ? THEN players.id END) as men', [Gender::Male->value])
            ->selectRaw('COUNT(DISTINCT CASE WHEN players.gender = ? THEN players.id END) as women', [Gender::Female->value])
            ->selectRaw('COUNT(DISTINCT CASE WHEN players.documents_verified_at IS NOT NULL THEN players.id END) as verified')
            ->toBase()
            ->get()
            ->keyBy('team_id');
    }
}
