<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\GamesEdition;
use App\Models\Season;
use App\Services\MedalService;
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
        return Season::with('tournament:id,name', 'sport:id,name')
            ->whereHas('standings')
            ->orderByRaw("FIELD(status, 'active', 'draft', 'closed')")->orderByDesc('start_date')
            ->get(['id', 'name', 'status', 'tournament_id', 'sport_id']);
    }

    public function editionOptions(): Collection
    {
        return GamesEdition::orderByDesc('start_date')->orderByDesc('id')->get(['id', 'name', 'is_active']);
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
        return ctype_digit($this->seasonId) ? Season::with('tournament:id,name', 'sport:id,name')->find($this->seasonId) : null;
    }

    private function validEdition(): ?GamesEdition
    {
        return ctype_digit($this->editionId) ? GamesEdition::find($this->editionId) : null;
    }
}
