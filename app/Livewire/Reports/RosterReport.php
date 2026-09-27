<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Filtros del Padron de Jugadores (PDF). Adaptado de
 * pichanga/RosterReport sin el selector de deporte: aqui no hay tablas por
 * deporte, la Season ya sabe el suyo. El equipo se elige entre las
 * INSCRIPCIONES (season_team) de la temporada, porque Team es un catalogo
 * global sin torneo propio.
 *
 * Solo arma la URL del PDF: RosterReportController la revalida con
 * SeasonPolicy::printReports (estas propiedades vienen del navegador).
 */
#[Layout('layouts.admin')]
#[Title('Padrón de Jugadores')]
class RosterReport extends Component
{
    public string $tournamentId = '';

    public string $seasonId = '';

    public string $seasonTeamId = '';

    public bool $showFilters = false;

    /** Modulo por rol (sin CRUD): se revalida en cada request, no solo en mount(). */
    public function boot(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'organizador']), 403);
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function updatedTournamentId(): void
    {
        $this->seasonId = '';
        $this->seasonTeamId = '';
    }

    public function updatedSeasonId(): void
    {
        $this->seasonTeamId = '';
    }

    /** Admin: todos los torneos; organizador: solo los suyos (organizer_id). */
    public function tournaments(): Collection
    {
        $user = auth()->user();

        return Tournament::query()
            ->unless($user->hasRole('admin'), fn ($q) => $q->where('organizer_id', $user->id))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }

    public function seasons(): Collection
    {
        $tournament = $this->selectedTournament();

        return $tournament
            ? $tournament->seasons()->orderByDesc('start_date')->orderBy('name')->get(['id', 'name', 'status'])
            : collect();
    }

    /** Inscripciones activas de la temporada (equipo = season_team, no Team suelto). */
    public function seasonTeams(): Collection
    {
        $season = $this->selectedSeason();

        return $season
            ? $season->seasonTeams()->where('is_active', true)->with('team:id,name')->get()->sortBy('team.name')->values()
            : collect();
    }

    /** Season elegida, solo si pertenece a un torneo visible para el usuario. */
    public function selectedSeason(): ?Season
    {
        $tournament = $this->selectedTournament();

        return $tournament && ctype_digit($this->seasonId)
            ? $tournament->seasons()->find((int) $this->seasonId)
            : null;
    }

    public function pdfUrl(): ?string
    {
        $season = $this->selectedSeason();

        if (! $season) {
            return null;
        }

        $seasonTeam = ctype_digit($this->seasonTeamId)
            ? SeasonTeam::where('season_id', $season->id)->find((int) $this->seasonTeamId)
            : null;

        return route('reports.roster.pdf', array_filter([
            'season_id' => $season->id,
            'season_team_id' => $seasonTeam?->id,
        ]));
    }

    public function render()
    {
        return view('livewire.reports.roster-report');
    }

    private function selectedTournament(): ?Tournament
    {
        return ctype_digit($this->tournamentId)
            ? $this->tournaments()->firstWhere('id', (int) $this->tournamentId)
            : null;
    }
}
