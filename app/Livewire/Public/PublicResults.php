<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\GamesEdition;
use App\Services\PublicResultsService;
use App\Services\SeasonOverviewService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pagina publica (sin login, ruta "/"): SOLO la programacion de partidos y
 * los resultados de una olimpiada, de todas sus competencias a la vez,
 * agrupados por dia. Posiciones, goleadores, sanciones y puntaje general
 * no se publican: viven en el panel.
 *
 * Todas las propiedades publicas vienen del navegador (y de la URL): la
 * olimpiada, el deporte y la competencia se resuelven SOLO entre lo
 * visible (PublicResultsService) y un valor ajeno se ignora.
 */
#[Layout('layouts.public')]
class PublicResults extends Component
{
    public const VIEWS = ['programacion', 'resultados'];

    private const PAGE = 40;

    /** Tope de filas: limit es publico, sin tope se podria pedir la olimpiada entera en un clic. */
    private const MAX = 400;

    #[Url(as: 'vista')]
    public string $view = 'programacion';

    #[Url(as: 'olimpiada')]
    public string $gamesEditionId = '';

    #[Url(as: 'deporte')]
    public string $sportId = '';

    #[Url(as: 'competencia')]
    public string $seasonId = '';

    #[Url(as: 'q')]
    public string $search = '';

    public int $limit = self::PAGE;

    public function setView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : self::VIEWS[0];
        $this->limit = self::PAGE;
    }

    public function updatedGamesEditionId(): void
    {
        $this->reset('sportId', 'seasonId', 'search', 'limit');
    }

    /** La competencia elegida puede no ser del nuevo deporte. */
    public function updatedSportId(): void
    {
        $this->reset('seasonId', 'limit');
    }

    public function updatedSeasonId(): void
    {
        $this->reset('limit');
    }

    public function updatedSearch(): void
    {
        $this->reset('limit');
    }

    public function clearFilters(): void
    {
        $this->reset('sportId', 'seasonId', 'search', 'limit');
    }

    public function more(): void
    {
        $this->limit = min($this->limit + self::PAGE, self::MAX);
    }

    public function render(PublicResultsService $public, SeasonOverviewService $overview)
    {
        $editions = $public->visibleGamesEditions();
        /** @var ?GamesEdition $edition */
        $edition = $editions->firstWhere('id', (int) $this->gamesEditionId) ?? $editions->first();
        $played = $this->view === 'resultados';

        $seasons = $edition ? $public->seasonsOf($edition->id) : collect();
        $sports = $seasons->pluck('sport')->unique('id')->sortBy('name')->values();
        $sport = $sports->firstWhere('id', (int) $this->sportId);
        $seasonOptions = $sport ? $seasons->where('sport_id', $sport->id)->values() : $seasons;
        $season = $seasonOptions->firstWhere('id', (int) $this->seasonId);

        $filters = [
            'sportId' => $sport?->id,
            'seasonId' => $season?->id,
            'search' => mb_substr(trim($this->search), 0, 60),
        ];

        $total = 0;
        $days = collect();
        $limit = max(self::PAGE, min($this->limit, self::MAX));

        if ($edition) {
            $query = $public->matchesOf($edition->id, $played, $filters);
            $total = (clone $query)->count();
            $days = $this->groupByDay(
                $query->with($overview->matchRowRelations())->limit($limit)->get()
                    ->map(fn ($match) => $overview->matchRow($match)),
            );
        }

        return view('livewire.public.public-results', [
            'editions' => $editions,
            'edition' => $edition,
            'sports' => $sports,
            'seasonOptions' => $seasonOptions,
            'played' => $played,
            'days' => $days,
            'shown' => $days->sum(fn (array $day) => $day['rows']->count()),
            'total' => $total,
            'canLoadMore' => $limit < min($total, self::MAX),
            // Totales de cada pestaña sin filtros: dicen cuanto hay, no cuanto coincide.
            'counts' => [
                'programacion' => $edition ? $public->matchesOf($edition->id, false)->count() : 0,
                'resultados' => $edition ? $public->matchesOf($edition->id, true)->count() : 0,
            ],
            'hasFilters' => $sport !== null || $season !== null || $filters['search'] !== '',
        ])->title(($played ? 'Resultados' : 'Programación').' — '.($edition?->name ?? config('app.name')));
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
}
