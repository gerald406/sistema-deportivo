<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\Gender;
use App\Models\Category;
use App\Models\Discipline;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\SeasonStage;
use App\Models\Sport;
use App\Models\Team;
use App\Services\PublicResultsService;
use App\Services\SeasonOverviewService;
use Illuminate\Support\Carbon;
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
 * Una pestaña por tarea (Programacion, Resultados, Podios y, si la
 * olimpiada tiene series, Series) y filtros opcionales que se combinan:
 * serie, fecha, deporte, prueba, nivel, genero, delegacion, estado del
 * podio y busqueda. Todo vive en la URL, asi que un enlace compartido abre
 * lo mismo.
 *
 * Todas las propiedades publicas vienen del navegador (y de la URL): cada
 * filtro se aplica SOLO si su valor esta entre las opciones visibles
 * (PublicResultsService); un valor ajeno se ignora.
 */
#[Layout('layouts.public')]
class PublicResults extends Component
{
    use WithPagination;

    public const VIEWS = ['programacion', 'resultados', 'podios', 'series'];

    public const PAGE_NAME = 'pagina';

    /** Filtros de la URL: "Limpiar" los vacia y cambiar cualquiera vuelve a la primera pagina. */
    private const FILTERS = ['series', 'date', 'sportId', 'disciplineId', 'categoryId', 'gender', 'teamId', 'state', 'search'];

    private const MATCHES_PER_PAGE = 20;

    private const PODIUMS_PER_PAGE = 18;

    #[Url(as: 'olimpiada')]
    public string $gamesEditionId = '';

    /** Vacio = la primera pestaña con contenido. */
    #[Url(as: 'vista')]
    public string $view = '';

    /** Vacio = todas; si no, id de la serie o PublicResultsService::FINALS. */
    #[Url(as: 'serie')]
    public string $series = '';

    /** Vacio = todas; si no, Y-m-d o PublicResultsService::NO_DATE. */
    #[Url(as: 'fecha')]
    public string $date = '';

    #[Url(as: 'deporte')]
    public string $sportId = '';

    #[Url(as: 'prueba')]
    public string $disciplineId = '';

    #[Url(as: 'nivel')]
    public string $categoryId = '';

    /** Vacio o un valor de App\Enums\Gender. */
    #[Url(as: 'genero')]
    public string $gender = '';

    #[Url(as: 'delegacion')]
    public string $teamId = '';

    /** Solo en Podios: vacio, PublicResultsService::DECIDED o PENDING. */
    #[Url(as: 'estado')]
    public string $state = '';

    /** Texto buscado y aplicado a la lista. */
    #[Url(as: 'q')]
    public string $search = '';

    /** Lo que se esta escribiendo en el buscador: alimenta las sugerencias hasta que se aplica. */
    public string $query = '';

    public function mount(): void
    {
        $this->query = $this->search;
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : '';
        $this->resetPage(self::PAGE_NAME);
    }

    /** Desde la pestaña Series: la serie queda como filtro de la pestaña elegida. */
    public function openSeries(string $key, string $view = 'programacion'): void
    {
        $this->series = $key;
        $this->setView($view);
    }

    public function updated(string $property): void
    {
        // La prueba es de un deporte: otro deporte la deja sin sentido.
        if ($property === 'sportId') {
            $this->disciplineId = '';
        }

        if ($property === 'gamesEditionId') {
            $this->reset(['view', 'query', ...self::FILTERS]);
        }

        // Quitar la ficha de la busqueda tambien vacia el buscador.
        if ($property === 'search') {
            $this->query = $this->search;
        }

        if (in_array($property, ['gamesEditionId', ...self::FILTERS], true)) {
            $this->resetPage(self::PAGE_NAME);
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['query', ...self::FILTERS]);
        $this->resetPage(self::PAGE_NAME);
    }

    /** Enter o "Buscar en...": el texto escrito filtra la lista (y, si se pide, cambia de pestaña). */
    public function applySearch(string $view = ''): void
    {
        $this->search = mb_substr(trim($this->query), 0, 60);
        $this->query = $this->search;

        if ($view !== '') {
            $this->view = in_array($view, self::VIEWS, true) ? $view : '';
        }

        $this->resetPage(self::PAGE_NAME);
    }

    /** Sugerencia de delegacion: filtra por ella. Una ajena a la olimpiada se ignora. */
    public function pickTeam(int $id, PublicResultsService $public): void
    {
        $edition = $this->currentEdition($public);

        if ($edition && $public->teamsOf($edition->id)->contains('id', $id)) {
            $this->teamId = (string) $id;
            $this->query = $this->search;
            $this->resetPage(self::PAGE_NAME);
        }
    }

    /** Sugerencia de competencia: sus fichas de deporte, prueba, nivel y genero (sin serie, para no ocultarla). */
    public function pickSeason(int $id, PublicResultsService $public): void
    {
        $edition = $this->currentEdition($public);
        $season = $edition ? $public->findSeasonIn($edition->id, $id) : null;

        if ($season) {
            $this->series = '';
            $this->sportId = (string) $season->sport_id;
            $this->disciplineId = (string) ($season->discipline_id ?? '');
            $this->categoryId = (string) ($season->category_id ?? '');
            $this->gender = $season->gender?->value ?? '';
            $this->query = $this->search;
            $this->resetPage(self::PAGE_NAME);
        }
    }

    /**
     * Sugerencia de participante: su delegacion. No se busca su apellido: en
     * los deportes de equipo el participante del partido es la delegacion.
     */
    public function pickPlayer(int $id, PublicResultsService $public): void
    {
        $edition = $this->currentEdition($public);
        $entry = $edition ? $public->findRosterEntry($edition->id, $id) : null;

        if ($entry) {
            $this->teamId = (string) $entry->seasonTeam->team_id;
            $this->query = $this->search;
            $this->resetPage(self::PAGE_NAME);
        }
    }

    public function render(PublicResultsService $public, SeasonOverviewService $overview)
    {
        $editions = $public->visibleGamesEditions();
        $edition = $this->currentEdition($public, $editions);

        if (! $edition) {
            return view('livewire.public.public-results', ['editions' => $editions, 'edition' => null])
                ->title('Programación y resultados — '.config('app.name'));
        }

        $series = $public->seriesOf($edition->id);
        $cards = $this->cards($series, $public->seriesSummary($edition->id));
        $card = $series->isEmpty() ? null : $cards->firstWhere('key', $this->series);
        $seriesFilter = match (true) {
            $card === null => null,
            $card['key'] === PublicResultsService::FINALS => PublicResultsService::FINALS,
            default => (int) $card['key'],
        };
        $totals = $card ?? $this->totals($cards);

        // Fichas: solo valores que existen en las competencias de la serie.
        $seasons = $public->seasonsIn($edition->id, $seriesFilter);
        $sports = $seasons->pluck('sport')->unique('id')->sortBy('name')->values();
        $sport = $sports->firstWhere('id', (int) $this->sportId);
        $inSport = $sport ? $seasons->where('sport_id', $sport->id) : $seasons;
        $disciplines = $sport ? $inSport->pluck('discipline')->filter()->unique('id')->sortBy('name')->values() : collect();
        $discipline = $disciplines->firstWhere('id', (int) $this->disciplineId);
        $scope = $discipline ? $inSport->where('discipline_id', $discipline->id) : $inSport;
        $categories = $scope->pluck('category')->filter()->unique('id')->sortBy('min_age')->values();
        $category = $categories->firstWhere('id', (int) $this->categoryId);
        $genders = collect(Gender::cases())->filter(fn (Gender $g) => $scope->contains('gender', $g))->values();
        $gender = $genders->first(fn (Gender $g) => $g->value === $this->gender);
        $teams = $public->teamsOf($edition->id);
        $team = $teams->firstWhere('id', (int) $this->teamId);

        $filters = [
            'series' => $seriesFilter,
            'sportId' => $sport?->id,
            'disciplineId' => $discipline?->id,
            'categoryId' => $category?->id,
            'gender' => $gender?->value,
            'teamId' => $team?->id,
            'search' => mb_substr(trim($this->search), 0, 60),
        ];

        // Sugerencias mientras se escribe algo distinto de lo ya aplicado.
        $term = mb_substr(trim($this->query), 0, 60);
        $suggestions = mb_strlen($term) >= 2 && $term !== $filters['search']
            ? $public->suggestionsFor($edition->id, $term) + ['term' => $term, 'counts' => [
                'programacion' => $public->scheduleOf($edition->id, ['search' => $term] + $filters)->reorder()->count(),
                'resultados' => $public->matchesOf($edition->id, true, ['search' => $term] + $filters)->reorder()->count(),
                'podios' => $public->podiumsOf($edition->id, ['search' => $term] + $filters)->reorder()->count(),
            ]]
            : null;

        $tabs = ['programacion' => 'Programación', 'resultados' => 'Resultados', 'podios' => 'Podios']
            + ($series->isNotEmpty() ? ['series' => 'Series'] : []);
        $counts = [
            'programacion' => $totals['pending'],
            'resultados' => $totals['played'],
            'podios' => $totals['podiums'],
        ];
        $view = $this->resolveView($tabs, $counts);

        $days = collect();
        $strip = collect();
        $podiums = collect();
        $page = null;
        $date = null;
        $state = null;
        $progress = null;
        // Los conteos de las fichas de deporte no dependen del estado del podio.
        $sportCounts = $view === 'series' ? [] : $public->countsBySport($edition->id, $view, $filters);

        if ($view === 'podios') {
            // Avance de lo que se esta viendo, sin importar el filtro de estado.
            $progress = [
                'label' => match (true) {
                    $discipline !== null => "{$sport->name} · {$discipline->name}",
                    $sport !== null => $sport->name,
                    $card !== null => $card['name'],
                    default => 'Toda la olimpiada',
                },
                'total' => $public->podiumsOf($edition->id, $filters)->reorder()->count(),
                'decided' => $public->podiumsOf($edition->id, ['state' => PublicResultsService::DECIDED] + $filters)->reorder()->count(),
            ];

            $state = in_array($this->state, [PublicResultsService::DECIDED, PublicResultsService::PENDING], true) ? $this->state : null;
            $filters['state'] = $state;
            $page = $public->podiumsOf($edition->id, $filters)
                ->paginate(self::PODIUMS_PER_PAGE, pageName: self::PAGE_NAME);
            $podiums = $this->groupBySport(collect($page->items()));
        } elseif ($view !== 'series') {
            $played = $view === 'resultados';
            $strip = $public->daysOf($edition->id, $played, $filters);
            $date = $strip->firstWhere('key', $this->date)['key'] ?? null;
            $filters['date'] = $date;

            if ($played) {
                $page = $public->matchesOf($edition->id, true, $filters)
                    ->with($overview->matchRowRelations())
                    ->paginate(self::MATCHES_PER_PAGE, pageName: self::PAGE_NAME);
                $days = $this->groupByDay(collect($page->items())->map(fn ($match) => $overview->matchRow($match)));
            } else {
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
            }
        }

        $active = $this->activeFilters($card, $date, $sport, $discipline, $category, $gender, $team, $state, $filters['search']);

        return view('livewire.public.public-results', [
            'editions' => $editions,
            'edition' => $edition,
            'cards' => $cards,
            'card' => $card,
            'hasSeries' => $series->isNotEmpty(),
            'totals' => $totals,
            'tabs' => $tabs,
            'counts' => $counts,
            'activeView' => $view,
            'sports' => $sports,
            'sport' => $sport,
            'sportCounts' => $sportCounts,
            'disciplines' => $disciplines,
            'discipline' => $discipline,
            'categories' => $categories,
            'category' => $category,
            'genders' => $genders,
            'activeGender' => $gender,
            'teams' => $teams,
            'team' => $team,
            'strip' => $strip,
            'activeDate' => $date,
            'activeState' => $state,
            'page' => $page,
            'days' => $days,
            'podiums' => $podiums,
            'activeFilters' => $active,
            'hasFilters' => $active !== [],
            'suggestions' => $suggestions,
            'progress' => $progress,
            // Letra (o Finales) de cada serie para las filas; sin series, ninguna.
            'seriesBadges' => $series->isEmpty() ? [] : $cards->mapWithKeys(fn (array $c) => [$c['key'] => ['mark' => $c['mark'], 'name' => $c['name']]])->all(),
        ])->layoutData(['bottomNav' => true])
            ->title("{$tabs[$view]} — {$edition->name}");
    }

    /**
     * Una tarjeta por serie y, al final, "Finales y podios". Sin series, una
     * sola con todo (la pestaña Series no aparece).
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

    /** Lo de todas las series juntas: las pestañas sin serie elegida. */
    private function totals(Collection $cards): array
    {
        return [
            'pending' => $cards->sum('pending'),
            'played' => $cards->sum('played'),
            'podiums' => $cards->sum('podiums'),
            'podiumStages' => $cards->sum('podiumStages'),
        ];
    }

    /** La pestaña pedida si existe; si no, la primera que tenga algo. */
    private function resolveView(array $tabs, array $counts): string
    {
        if (isset($tabs[$this->view])) {
            return $this->view;
        }

        foreach ($counts as $key => $count) {
            if ($count > 0) {
                return $key;
            }
        }

        return 'programacion';
    }

    /**
     * Los filtros aplicados, como fichas que se quitan una a una. La
     * propiedad es la que se vacia al quitarla.
     *
     * @return list<array{property: string, label: string}>
     */
    private function activeFilters(?array $card, ?string $date, ?Sport $sport, ?Discipline $discipline, ?Category $category, ?Gender $gender, ?Team $team, ?string $state, string $search): array
    {
        return array_values(array_filter([
            $card ? ['property' => 'series', 'label' => $card['mark'] === null ? 'Finales' : $card['name']] : null,
            $date ? ['property' => 'date', 'label' => $date === PublicResultsService::NO_DATE
                ? 'Fecha por confirmar'
                : ucfirst(Carbon::parse($date)->locale('es')->isoFormat('ddd D [de] MMM'))] : null,
            $sport ? ['property' => 'sportId', 'label' => $sport->name] : null,
            $discipline ? ['property' => 'disciplineId', 'label' => $discipline->name] : null,
            $category ? ['property' => 'categoryId', 'label' => $category->name] : null,
            $gender ? ['property' => 'gender', 'label' => $gender->label()] : null,
            $team ? ['property' => 'teamId', 'label' => $team->name] : null,
            $state ? ['property' => 'state', 'label' => $state === PublicResultsService::DECIDED ? 'Definidos' : 'Por disputar'] : null,
            $search !== '' ? ['property' => 'search', 'label' => "«{$search}»"] : null,
        ]));
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
     * @return Collection<int, array{key: string, date: ?Carbon, rows: Collection}>
     */
    private function groupByDay(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => $row['scheduledAt']?->format('Y-m-d') ?? PublicResultsService::NO_DATE)
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
     * @return Collection<int, array{sportId: int, sport: string, stages: Collection}>
     */
    private function groupBySport(Collection $stages): Collection
    {
        return $stages
            ->groupBy(fn (SeasonStage $stage) => $stage->season->sport_id)
            ->map(fn (Collection $group, int $sportId) => ['sportId' => $sportId, 'sport' => $group->first()->season->sport->name, 'stages' => $group])
            ->values();
    }

    /** La olimpiada que se esta viendo: la elegida si es visible, si no la mas reciente. */
    private function currentEdition(PublicResultsService $public, ?Collection $editions = null): ?GamesEdition
    {
        $editions ??= $public->visibleGamesEditions();

        return $editions->firstWhere('id', (int) $this->gamesEditionId) ?? $editions->first();
    }
}
