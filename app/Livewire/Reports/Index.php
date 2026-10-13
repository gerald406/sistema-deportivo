<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Season;
use App\Services\SeasonService;
use App\Services\StandingService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reporte de SOLO LECTURA: tabla de posiciones de una competencia. La
 * mantiene el calculo sincrono de StandingService; aqui no se escribe
 * nada. El puntaje general de la olimpiada tiene su propia pantalla
 * (Reports\GeneralScore).
 *
 * Alcance por rol: competencias via SeasonService::scopeVisibleTo()
 * (organizador: sus olimpiadas; delegado: donde su equipo esta inscrito).
 * La seleccion viene de la URL (?competencia=), por eso se busca acotada.
 */
#[Layout('layouts.admin')]
#[Title('Reportes')]
class Index extends Component
{
    #[Url(as: 'competencia')]
    public string $seasonId = '';

    /** Reportes: permiso reports.view (los 3 roles). Sin Policy de modelo. */
    public function boot(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function mount(): void
    {
        if (! $this->validSeason()) {
            $this->seasonId = (string) ($this->seasonOptions()->first()?->id ?? '');
        }
    }

    /** Competencias que tienen tabla calculada (enfrentamientos por equipos). */
    public function seasonOptions(): Collection
    {
        return app(SeasonService::class)->scopeVisibleTo(Season::query(), auth()->user())
            ->with('gamesEdition:id,name', 'sport:id,name')
            ->whereHas('standings')
            ->orderByRaw("FIELD(status, 'active', 'draft', 'closed')")->orderByDesc('start_date')
            ->get(['id', 'name', 'status', 'games_edition_id', 'sport_id']);
    }

    public function render(StandingService $standings)
    {
        $season = $this->validSeason();

        return view('livewire.reports.index', [
            'season' => $season,
            'table' => $season ? $standings->tableFor($season) : collect(),
            'scoring' => $season?->scoringConfigs()->orderBy('id')->first(),
        ]);
    }

    private function validSeason(): ?Season
    {
        return ctype_digit($this->seasonId)
            ? app(SeasonService::class)->scopeVisibleTo(Season::query(), auth()->user())->with('gamesEdition:id,name', 'sport:id,name')->find($this->seasonId)
            : null;
    }
}
