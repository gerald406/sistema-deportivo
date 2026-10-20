<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\SeasonStage;
use App\Services\PublicResultsService;
use App\Services\SeasonOverviewService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pagina publica (sin login, ruta "/"): SOLO la programacion de partidos,
 * los resultados y los podios de una olimpiada. Posiciones, goleadores,
 * sanciones y puntaje general no se publican: viven en el panel.
 *
 * Se recorre por serie: la portada muestra una tarjeta por serie (A, B, C,
 * D) y una de "Finales y podios"; al abrir una, su programacion, resultados
 * y podios, paginados. Una olimpiada sin series abre directo ese detalle.
 *
 * Todas las propiedades publicas vienen del navegador (y de la URL): la
 * olimpiada, la serie, el deporte y la competencia se resuelven SOLO entre
 * lo visible (PublicResultsService) y un valor ajeno se ignora.
 */
#[Layout('layouts.public')]
class PublicResults extends Component
{
    use WithPagination;

    public const VIEWS = ['programacion', 'resultados', 'podios'];

    public const PAGE_NAME = 'pagina';

    private const MATCHES_PER_PAGE = 20;

    private const PODIUMS_PER_PAGE = 18;

    #[Url(as: 'olimpiada')]
    public string $gamesEditionId = '';

    /** Id de la serie, PublicResultsService::FINALS o vacio (portada de tarjetas). */
    #[Url(as: 'serie')]
    public string $series = '';

    /** Vacio = la primera pestaña con contenido. */
    #[Url(as: 'vista')]
    public string $view = '';

    #[Url(as: 'deporte')]
    public string $sportId = '';

    #[Url(as: 'competencia')]
    public string $seasonId = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function openSeries(string $key): void
    {
        $this->series = $key;
        $this->reset('view', 'sportId', 'seasonId', 'search');
        $this->resetPage(self::PAGE_NAME);
    }

    public function closeSeries(): void
    {
        $this->openSeries('');
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : '';
        $this->resetPage(self::PAGE_NAME);
    }

    public function updatedGamesEditionId(): void
    {
        $this->reset('series', 'view', 'sportId', 'seasonId', 'search');
        $this->resetPage(self::PAGE_NAME);
    }

    /** La competencia elegida puede no ser del nuevo deporte. */
    public function updatedSportId(): void
    {
        $this->reset('seasonId');
        $this->resetPage(self::PAGE_NAME);
    }

    public function updatedSeasonId(): void
    {
        $this->resetPage(self::PAGE_NAME);
    }

    public function updatedSearch(): void
    {
        $this->resetPage(self::PAGE_NAME);
    }

    public function selectSport(string $sportId): void
    {
        $this->sportId = $sportId;
        $this->updatedSportId();
    }

    public function clearFilters(): void
    {
        $this->reset('sportId', 'seasonId', 'search');
        $this->resetPage(self::PAGE_NAME);
    }

    public function render(PublicResultsService $public, SeasonOverviewService $overview)
    {
        $editions = $public->visibleGamesEditions();
        /** @var ?GamesEdition $edition */
        $edition = $editions->firstWhere('id', (int) $this->gamesEditionId) ?? $editions->first();

        $series = $edition ? $public->seriesOf($edition->id) : collect();
        $summary = $edition ? $public->seriesSummary($edition->id) : [];
        $cards = $this->cards($series, $summary);
        $card = $this->resolveCard($cards, $series->isEmpty());

        $data = [
            'editions' => $editions,
            'edition' => $edition,
            'cards' => $cards,
            'card' => $card,
            'hasSeries' => $series->isNotEmpty(),
        ];

        if (! $edition || ! $card) {
            return view('livewire.public.public-results', $data)
                ->title('Programación y resultados — '.($edition?->name ?? config('app.name')));
        }

        $filter = $card['key'] === PublicResultsService::FINALS ? PublicResultsService::FINALS : (int) $card['key'];
        $seasons = $public->seasonsIn($edition->id, $filter);
        $sports = $seasons->pluck('sport')->unique('id')->sortBy('name')->values();
        $sport = $sports->firstWhere('id', (int) $this->sportId);
        $seasonOptions = $sport ? $seasons->where('sport_id', $sport->id)->values() : collect();
        $season = $seasonOptions->firstWhere('id', (int) $this->seasonId);

        $filters = [
            'series' => $filter,
            'sportId' => $sport?->id,
            'seasonId' => $season?->id,
            'search' => mb_substr(trim($this->search), 0, 60),
        ];

        $counts = [
            'programacion' => $card['pending'],
            'resultados' => $card['played'],
            'podios' => $card['podiums'],
        ];
        $tabs = array_filter(
            ['programacion' => 'Programación', 'resultados' => 'Resultados', 'podios' => 'Podios'],
            fn (string $key) => $key !== 'podios' || $card['podiumStages'] > 0,
            ARRAY_FILTER_USE_KEY,
        );
        $view = $this->resolveView($tabs, $counts);

        $days = collect();
        $podiums = collect();

        if ($view === 'podios') {
            $page = $public->podiumsOf($edition->id, $filters)
                ->paginate(self::PODIUMS_PER_PAGE, pageName: self::PAGE_NAME);
            $podiums = $this->groupBySport(collect($page->items()));
        } elseif ($view === 'programacion') {
            // Partidos y pruebas de podio programadas, paginados juntos (D32).
            $page = $public->scheduleOf($edition->id, $filters)
                ->paginate(self::MATCHES_PER_PAGE, pageName: self::PAGE_NAME);
            $items = collect($page->items());
            $matches = GameMatch::with($overview->matchRowRelations())
                ->whereKey($items->where('kind', 'match')->pluck('id'))->get()->keyBy('id');
            $stages = SeasonStage::with(['season.sport', 'season.discipline', 'venue'])
                ->whereKey($items->where('kind', 'stage')->pluck('id'))->get()->keyBy('id');
            $days = $this->groupByDay($items->map(fn (object $item) => $item->kind === 'match'
                ? $overview->matchRow($matches[$item->id])
                : $overview->stageRow($stages[$item->id])));
        } else {
            $page = $public->matchesOf($edition->id, $view === 'resultados', $filters)
                ->with($overview->matchRowRelations())
                ->paginate(self::MATCHES_PER_PAGE, pageName: self::PAGE_NAME);
            $days = $this->groupByDay(collect($page->items())->map(fn ($match) => $overview->matchRow($match)));
        }

        $label = ['programacion' => 'Programación', 'resultados' => 'Resultados', 'podios' => 'Podios'][$view];

        return view('livewire.public.public-results', $data + [
            'sports' => $sports,
            'sport' => $sport,
            'seasonOptions' => $seasonOptions,
            'tabs' => $tabs,
            'counts' => $counts,
            'activeView' => $view,
            'page' => $page,
            'days' => $days,
            'podiums' => $podiums,
            'hasFilters' => $sport !== null || $season !== null || $filters['search'] !== '',
        ])->title("{$label} {$card['name']} — {$edition->name}");
    }

    /**
     * Una tarjeta por serie y, al final, "Finales y podios". Sin series, una
     * sola con todo, que se abre directamente.
     *
     * @param  Collection<int, GamesEditionGroup>  $series
     * @return Collection<int, array<string, mixed>>
     */
    private function cards(Collection $series, array $summary): Collection
    {
        $empty = ['pending' => 0, 'played' => 0, 'today' => 0, 'nextAt' => null, 'podiums' => 0, 'podiumStages' => 0];

        $cards = $series->map(fn (GamesEditionGroup $group) => [
            'key' => (string) $group->id,
            'name' => $group->name,
            'mark' => $this->seriesMark($group->name),
            'teams' => $group->editionTeams->pluck('team.name')->filter()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values(),
        ] + ($summary[(string) $group->id] ?? $empty));

        return $cards->push([
            'key' => PublicResultsService::FINALS,
            'name' => $series->isEmpty() ? 'Programación y resultados' : 'Finales y podios',
            'mark' => null,
            'teams' => collect(),
        ] + ($summary[PublicResultsService::FINALS] ?? $empty))->values();
    }

    /** La tarjeta abierta, solo entre las de esta olimpiada; sin series se abre la unica. */
    private function resolveCard(Collection $cards, bool $withoutSeries): ?array
    {
        if ($withoutSeries) {
            return $cards->first();
        }

        return $this->series === '' ? null : $cards->firstWhere('key', $this->series);
    }

    /** La pestaña pedida si existe; si no, la primera que tenga algo. */
    private function resolveView(array $tabs, array $counts): string
    {
        if (isset($tabs[$this->view])) {
            return $this->view;
        }

        foreach (array_keys($tabs) as $key) {
            if ($counts[$key] > 0) {
                return $key;
            }
        }

        return array_key_first($tabs);
    }

    /** "Serie A" -> "A"; un nombre sin letra final se marca con su inicial. */
    private function seriesMark(string $name): string
    {
        $last = mb_strrchr(' '.trim($name), ' ');
        $last = trim((string) $last);

        return mb_strlen($last) <= 2 ? mb_strtoupper($last) : mb_strtoupper(mb_substr(trim($name), 0, 1));
    }

    /**
     * Las filas ya vienen ordenadas; aqui solo se cortan por dia. Lo que no
     * tiene fecha va en un grupo propio al final.
     *
     * @return Collection<int, array{key: string, date: ?\Illuminate\Support\Carbon, rows: Collection}>
     */
    private function groupByDay(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => $row['scheduledAt']?->format('Y-m-d') ?? 'sin-fecha')
            ->map(fn (Collection $group, string $key) => [
                'key' => $key,
                'date' => $group->first()['scheduledAt']?->copy()->startOfDay(),
                'rows' => $group,
            ])
            ->values();
    }

    /**
     * Podios de la pagina, cortados por deporte (ya vienen ordenados).
     *
     * @param  Collection<int, SeasonStage>  $stages
     * @return Collection<int, array{sport: string, stages: Collection}>
     */
    private function groupBySport(Collection $stages): Collection
    {
        return $stages
            ->groupBy(fn (SeasonStage $stage) => $stage->season->sport->name)
            ->map(fn (Collection $group, string $sport) => ['sport' => $sport, 'stages' => $group])
            ->values();
    }
}
