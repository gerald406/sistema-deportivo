<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Category;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\GamesEdition;
use App\Services\SeasonTeamService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Filtros del Padron de Jugadores (PDF). Adaptado de
 * pichanga/RosterReport sin el selector de deporte: aqui no hay tablas por
 * deporte, la Season ya sabe el suyo. El equipo se elige entre las
 * INSCRIPCIONES (season_team) de la temporada, porque Team es un catalogo
 * global sin olimpiada propia.
 *
 * Alcance por rol: las opciones salen de las inscripciones que el usuario
 * ve en /inscripciones (SeasonTeamService::scopeVisibleTo()). El delegado
 * solo ve olimpiadas/temporadas/categorias donde su equipo esta inscrito y
 * debe elegir su equipo (no hay "todos los equipos" para el).
 *
 * Solo arma la URL del PDF: RosterReportController la revalida
 * (SeasonTeamPolicy::printRoster / SeasonPolicy::printReports), porque
 * estas propiedades vienen del navegador.
 */
#[Layout('layouts.admin')]
#[Title('Padrón de Jugadores')]
class RosterReport extends Component
{
    public string $gamesEditionId = '';

    public string $seasonId = '';

    /** Categoria de la inscripcion (division por edad/genero), no el deporte. */
    public string $categoryId = '';

    public string $seasonTeamId = '';

    public bool $showFilters = false;

    /** Modulo por rol (sin CRUD): se revalida en cada request, no solo en mount(). */
    public function boot(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'organizador', 'delegado']), 403);
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function updatedGamesEditionId(): void
    {
        $this->seasonId = '';
        $this->categoryId = '';
        $this->seasonTeamId = '';
    }

    public function updatedSeasonId(): void
    {
        $this->categoryId = '';
        $this->seasonTeamId = '';
    }

    public function updatedCategoryId(): void
    {
        $this->seasonTeamId = '';
    }

    /**
     * Admin: todas las olimpiadas. Organizador: las suyas (organizer_id).
     * Delegado: aquellos con alguna inscripcion activa de su equipo.
     */
    public function gamesEditions(): Collection
    {
        $user = auth()->user();

        return GamesEdition::query()
            ->unless($user->hasRole('admin'), fn ($q) => $q->where(fn ($q) => $q
                ->when($user->hasRole('organizador'), fn ($q) => $q->orWhere('organizer_id', $user->id))
                ->orWhereHas('seasons.seasonTeams', fn ($st) => $this->scopeVisibleSeasonTeams($st))))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'organizer_id']);
    }

    /** Temporadas de la olimpiada; si no lo gestiona, solo donde su equipo esta inscrito. */
    public function seasons(): Collection
    {
        $gamesEdition = $this->selectedGamesEdition();

        return $gamesEdition
            ? $gamesEdition->seasons()
                ->unless($this->managesGamesEdition($gamesEdition), fn ($q) => $q->whereHas('seasonTeams', fn ($st) => $this->scopeVisibleSeasonTeams($st)))
                ->orderByDesc('start_date')->orderBy('name')
                ->get(['id', 'name', 'status', 'games_edition_id'])
            : collect();
    }

    /** Categorias de las inscripciones visibles de la temporada elegida. */
    public function categories(): Collection
    {
        $season = $this->selectedSeason();

        if (! $season) {
            return collect();
        }

        $ids = $this->scopeVisibleSeasonTeams(SeasonTeam::query())
            ->where('season_id', $season->id)
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id');

        return Category::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'gender']);
    }

    /** Inscripciones activas y visibles de la temporada (y categoria, si se eligio). */
    public function seasonTeams(): Collection
    {
        $season = $this->selectedSeason();

        return $season
            ? $this->scopeVisibleSeasonTeams(SeasonTeam::query())
                ->where('season_id', $season->id)
                ->when(ctype_digit($this->categoryId), fn ($q) => $q->where('category_id', (int) $this->categoryId))
                ->with(['team:id,name', 'category:id,name'])
                ->get()->sortBy('team.name')->values()
            : collect();
    }

    /** Season elegida, solo si esta entre las opciones visibles para el usuario. */
    public function selectedSeason(): ?Season
    {
        return ctype_digit($this->seasonId)
            ? $this->seasons()->firstWhere('id', (int) $this->seasonId)
            : null;
    }

    /** Padron de todos los equipos: solo admin u organizador de la olimpiada. */
    public function canPrintAll(): bool
    {
        $season = $this->selectedSeason();

        return $season !== null && Gate::allows('printReports', $season);
    }

    public function pdfUrl(): ?string
    {
        $season = $this->selectedSeason();

        if (! $season) {
            return null;
        }

        $seasonTeam = ctype_digit($this->seasonTeamId)
            ? $this->seasonTeams()->firstWhere('id', (int) $this->seasonTeamId)
            : null;

        if (! $seasonTeam && ! $this->canPrintAll()) {
            return null;
        }

        $categoryId = ! $seasonTeam && ctype_digit($this->categoryId) && $this->categories()->contains('id', (int) $this->categoryId)
            ? (int) $this->categoryId
            : null;

        return route('reports.roster.pdf', array_filter([
            'season_id' => $season->id,
            'season_team_id' => $seasonTeam?->id,
            'category_id' => $categoryId,
        ]));
    }

    public function render()
    {
        return view('livewire.reports.roster-report');
    }

    private function selectedGamesEdition(): ?GamesEdition
    {
        return ctype_digit($this->gamesEditionId)
            ? $this->gamesEditions()->firstWhere('id', (int) $this->gamesEditionId)
            : null;
    }

    private function managesGamesEdition(GamesEdition $gamesEdition): bool
    {
        $user = auth()->user();

        return $user->hasRole('admin')
            || ($user->hasRole('organizador') && $gamesEdition->organizer_id === $user->id);
    }

    /** Inscripciones activas dentro del alcance del usuario (misma regla que /inscripciones). */
    private function scopeVisibleSeasonTeams(Builder $query): Builder
    {
        return app(SeasonTeamService::class)
            ->scopeVisibleTo($query, auth()->user())
            ->where('season_team.is_active', true);
    }
}
