<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\Season;
use App\Services\PublicResultsService;
use App\Services\SeasonOverviewService;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Resultados publicos (sin login). Adaptado de pichanga/Public/PublicResults
 * SIN la rama futbol/voley: se elige Olimpiada -> Temporada y cada temporada
 * ya sabe su deporte/disciplina.
 *
 * Todas las propiedades publicas vienen del navegador: la temporada se
 * resuelve SOLO entre las visibles (PublicResultsService) y las pestañas
 * solo entre las que esa temporada soporta (SeasonOverviewService::sections,
 * el mismo criterio que el Reporte Gerencial).
 */
class PublicResults extends Component
{
    public const TABS = ['standings', 'results', 'schedule', 'scorers', 'suspended', 'yellows'];

    public string $gamesEditionId = '';

    public string $seasonId = '';

    public string $activeTab = 'standings';

    /** Jornada elegida en el select de Resultados ('' = la ultima completada). */
    public string $selectedResultMatchdayId = '';

    public function mount(PublicResultsService $public): void
    {
        $first = $public->visibleGamesEditions()->first();
        $this->gamesEditionId = $first ? (string) $first->id : '';
        $this->selectFirstSeason($public);
    }

    public function updatedGamesEditionId(PublicResultsService $public): void
    {
        $this->selectFirstSeason($public);
    }

    public function updatedSeasonId(): void
    {
        $this->activeTab = 'standings';
        $this->selectedResultMatchdayId = '';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, self::TABS, true) ? $tab : 'standings';
    }

    public function render(PublicResultsService $public, SeasonOverviewService $overview)
    {
        $gamesEditions = $public->visibleGamesEditions();
        $seasons = ctype_digit($this->gamesEditionId) ? $public->seasonsOf((int) $this->gamesEditionId) : collect();
        $season = ctype_digit($this->seasonId) && $seasons->contains('id', (int) $this->seasonId)
            ? $public->findVisibleSeason((int) $this->seasonId)
            : null;

        $data = ['gamesEditions' => $gamesEditions, 'seasons' => $seasons, 'season' => $season, 'tabs' => []];

        if ($season) {
            $sections = $overview->sections($season);
            $tabs = array_keys(array_filter([
                'standings' => $sections['standings'],
                'results' => true,
                'schedule' => true,
                'scorers' => $sections['scorers'],
                'suspended' => $sections['suspensions'],
                'yellows' => $sections['yellows'],
            ]));

            // Pestaña no soportada por esta temporada (o manipulada): la primera valida.
            if (! in_array($this->activeTab, $tabs, true)) {
                $this->activeTab = $tabs[0];
            }

            $data['tabs'] = $tabs;
            $data += $this->tabData($season, $overview);
        }

        return view('livewire.public.public-results', $data);
    }

    /** Solo se consulta lo que muestra la pestaña activa. */
    private function tabData(Season $season, SeasonOverviewService $overview): array
    {
        switch ($this->activeTab) {
            case 'standings':
                $table = $overview->standings($season);

                return ['standings' => $table, 'standingColumns' => $overview->standingColumns($season, $table)];

            case 'results':
                $completed = $overview->completedMatchdays($season);
                // Respeta la jornada elegida solo si es de ESTA temporada; si no, la mas reciente.
                $matchday = ctype_digit($this->selectedResultMatchdayId)
                    ? $completed->firstWhere('id', (int) $this->selectedResultMatchdayId)
                    : null;
                $matchday ??= $completed->first();

                return [
                    'completedMatchdays' => $completed,
                    'lastMatchday' => $matchday,
                    'lastResults' => $matchday ? $overview->matchRows($matchday, onlyDecided: true) : collect(),
                ];

            case 'schedule':
                $next = $overview->nextMatchday($season);

                return ['nextMatchday' => $next, 'nextGames' => $next ? $overview->matchRows($next, onlyDecided: false) : collect()];

            case 'scorers':
                return ['topScorers' => $overview->topScorers($season, 10)];

            case 'suspended':
                return ['suspensions' => $overview->activeSuspensions($season)];

            case 'yellows':
                return ['yellowCardRisks' => $overview->playersWithTwoYellows($season)];
        }

        return [];
    }

    private function selectFirstSeason(PublicResultsService $public): void
    {
        /** @var Collection<int, Season> $seasons */
        $seasons = ctype_digit($this->gamesEditionId) ? $public->seasonsOf((int) $this->gamesEditionId) : collect();
        $this->seasonId = $seasons->isNotEmpty() ? (string) $seasons->first()->id : '';
        $this->activeTab = 'standings';
        $this->selectedResultMatchdayId = '';
    }
}
