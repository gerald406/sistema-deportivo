<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Season;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Filtro del Reporte Gerencial (PDF). Adaptado de pichanga/ManagerialReport:
 * solo pide la Temporada (no deporte + campeonato por separado): la Season
 * ya sabe su deporte y su torneo. Mismo alcance que la referencia: el admin
 * ve todas, el organizador solo las de sus torneos.
 *
 * Solo arma la URL: ManagerialReportController revalida con
 * SeasonPolicy::printReports.
 */
#[Layout('layouts.admin')]
#[Title('Reporte Gerencial')]
class ManagerialReport extends Component
{
    public string $seasonId = '';

    public bool $showInfo = true;

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'organizador']), 403);
    }

    public function toggleInfo(): void
    {
        $this->showInfo = ! $this->showInfo;
    }

    /** Temporadas agrupables por torneo; organizador: solo las de sus torneos. */
    public function seasons(): Collection
    {
        $user = auth()->user();

        return Season::query()
            ->with('tournament:id,name', 'sport:id,name')
            ->unless($user->hasRole('admin'), fn ($q) => $q->whereHas('tournament', fn ($t) => $t->where('organizer_id', $user->id)))
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get(['id', 'tournament_id', 'sport_id', 'name', 'status'])
            ->sortBy('tournament.name')
            ->values();
    }

    public function pdfUrl(): ?string
    {
        $season = ctype_digit($this->seasonId) ? $this->seasons()->firstWhere('id', (int) $this->seasonId) : null;

        return $season ? route('reports.managerial.pdf', ['season_id' => $season->id]) : null;
    }

    public function render()
    {
        return view('livewire.reports.managerial-report');
    }
}
