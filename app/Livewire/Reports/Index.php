<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\GamesEdition;
use App\Models\Season;
use App\Services\GamesEditionService;
use App\Services\MedalService;
use App\Services\SeasonService;
use App\Services\StandingService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reportes de SOLO LECTURA: tabla de posiciones de una temporada y
 * medallero de una edicion. Los datos los mantiene el calculo sincrono de
 * StandingService / MedalService; aqui no se escribe nada.
 *
 * Alcance por rol: temporadas via SeasonService::scopeVisibleTo()
 * (organizador: sus torneos; delegado: donde su equipo esta inscrito) y
 * ediciones via GamesEditionService::scopeVisibleTo(). La seleccion viene de
 * la URL (?temporada=, ?edicion=), por eso tambien se busca acotada.
 */
#[Layout('layouts.admin')]
#[Title('Reportes')]
class Index extends Component
{
    #[Url(as: 'temporada')]
    public string $seasonId = '';

    #[Url(as: 'edicion')]
    public string $editionId = '';

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

        if (! $this->validEdition()) {
            $this->editionId = (string) ($this->editionOptions()->first()?->id ?? '');
        }
    }

    /** Temporadas que tienen tabla calculada (enfrentamientos por equipos). */
    public function seasonOptions(): Collection
    {
        return app(SeasonService::class)->scopeVisibleTo(Season::query(), auth()->user())
            ->with('tournament:id,name', 'sport:id,name')
            ->whereHas('standings')
            ->orderByRaw("FIELD(status, 'active', 'draft', 'closed')")->orderByDesc('start_date')
            ->get(['id', 'name', 'status', 'tournament_id', 'sport_id']);
    }

    public function editionOptions(): Collection
    {
        return app(GamesEditionService::class)->scopeVisibleTo(GamesEdition::query(), auth()->user())
            ->orderByDesc('start_date')->orderByDesc('id')->get(['id', 'name', 'is_active']);
    }

    public function render(StandingService $standings, MedalService $medals)
    {
        $season = $this->validSeason();
        $edition = $this->validEdition();

        return view('livewire.reports.index', [
            'season' => $season,
            'table' => $season ? $standings->tableFor($season) : collect(),
            'scoring' => $season?->scoringConfigs()->orderBy('id')->first(),
            'edition' => $edition,
            'medalTable' => $edition ? $medals->tableFor($edition) : collect(),
        ]);
    }

    private function validSeason(): ?Season
    {
        return ctype_digit($this->seasonId)
            ? app(SeasonService::class)->scopeVisibleTo(Season::query(), auth()->user())->with('tournament:id,name', 'sport:id,name')->find($this->seasonId)
            : null;
    }

    private function validEdition(): ?GamesEdition
    {
        return ctype_digit($this->editionId)
            ? app(GamesEditionService::class)->scopeVisibleTo(GamesEdition::query(), auth()->user())->find($this->editionId)
            : null;
    }
}
