<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SeasonTeamPlayerStatus;
use App\Models\Season;
use App\Models\Tournament;
use App\Services\PlayerCardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Selector de Fichas de Inscripcion. Adaptado de
 * pichanga/PlayerRegistrationSelector: alli Player tiene team_id y Team
 * tiene championship_id; aqui el filtro es Torneo -> Temporada -> Equipo
 * inscrito (season_team) y lo que se lista son fichas del plantel
 * (season_team_player), porque el mismo jugador puede estar en varias
 * temporadas con distinto equipo.
 *
 * Alcance: PlayerCardService::printableSeasonTeams() = la misma regla que
 * SeasonTeamPlayerPolicy::printCard (el delegado ve donde es el delegado
 * EFECTIVO). El Controller revalida cada id con la Policy.
 */
#[Layout('layouts.admin')]
#[Title('Fichas de Inscripción')]
class PlayerRegistrationSelector extends Component
{
    /** Tope por PDF (el Controller aplica el mismo). */
    public const MAX_SELECTION = 150;

    public string $tournamentId = '';

    public string $seasonId = '';

    public string $seasonTeamId = '';

    public string $search = '';

    /** @var array<int, int> ids de season_team_player */
    public array $selected = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'organizador', 'delegado']), 403);
    }

    public function updatedTournamentId(): void
    {
        $this->seasonId = '';
        $this->seasonTeamId = '';
        $this->selected = [];
    }

    public function updatedSeasonId(): void
    {
        $this->seasonTeamId = '';
        $this->selected = [];
    }

    public function updatedSeasonTeamId(): void
    {
        $this->selected = [];
    }

    public function toggleAll(bool $checked): void
    {
        $this->selected = $checked
            ? $this->entriesQuery()->limit(self::MAX_SELECTION)->pluck('season_team_player.id')->map(fn ($id) => (int) $id)->all()
            : [];
    }

    public function tournaments(): Collection
    {
        $ids = $this->printableSeasonTeams()->join('seasons', 'seasons.id', '=', 'season_team.season_id')->distinct()->pluck('seasons.tournament_id');

        return Tournament::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    public function seasons(): Collection
    {
        if (! ctype_digit($this->tournamentId)) {
            return collect();
        }

        $ids = $this->scopedSeasonTeams()->distinct()->pluck('season_team.season_id');

        return Season::whereIn('id', $ids)->where('tournament_id', (int) $this->tournamentId)
            ->orderByDesc('start_date')->orderBy('name')->get(['id', 'name', 'status']);
    }

    public function seasonTeams(): Collection
    {
        return ctype_digit($this->seasonId)
            ? $this->scopedSeasonTeams()->where('season_team.season_id', (int) $this->seasonId)->with('team:id,name')->get()->sortBy('team.name')->values()
            : collect();
    }

    public function render()
    {
        return view('livewire.player-registration-selector', [
            'entries' => ctype_digit($this->seasonId)
                ? $this->entriesQuery()
                    ->with(['player:id,first_name,last_name,dni', 'seasonTeam.team:id,name'])
                    ->limit(500)
                    ->get()
                : collect(),
        ]);
    }

    private function printableSeasonTeams(): Builder
    {
        return app(PlayerCardService::class)->printableSeasonTeams(auth()->user());
    }

    /** printableSeasonTeams() acotado al torneo elegido. */
    private function scopedSeasonTeams(): Builder
    {
        return $this->printableSeasonTeams()
            ->when(ctype_digit($this->tournamentId), fn ($q) => $q->whereHas('season', fn ($s) => $s->where('tournament_id', (int) $this->tournamentId)));
    }

    /** Fichas visibles con los filtros actuales (sin las dadas de baja, como la referencia con is_active). */
    private function entriesQuery(): Builder
    {
        $term = trim($this->search);

        return app(PlayerCardService::class)->printableEntries(auth()->user())
            ->select('season_team_player.*')
            ->join('players', 'players.id', '=', 'season_team_player.player_id')
            ->join('season_team', 'season_team.id', '=', 'season_team_player.season_team_id')
            ->where('season_team_player.status', '!=', SeasonTeamPlayerStatus::Inactive->value)
            ->when(ctype_digit($this->seasonId), fn ($q) => $q->where('season_team.season_id', (int) $this->seasonId))
            ->when(ctype_digit($this->seasonTeamId), fn ($q) => $q->where('season_team.id', (int) $this->seasonTeamId))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('players.first_name', 'like', "%{$term}%")
                ->orWhere('players.last_name', 'like', "%{$term}%")
                ->orWhere('players.dni', 'like', "%{$term}%")))
            ->orderBy('players.last_name')
            ->orderBy('players.first_name');
    }
}
