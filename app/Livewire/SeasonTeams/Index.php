<?php

declare(strict_types=1);

namespace App\Livewire\SeasonTeams;

use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Category;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\SeasonTeamService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Inscripciones')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    public string $seasonFilter = '';

    public string $categoryFilter = '';

    /** all | active | inactive */
    public string $statusFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'team_name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'season_id' => '',
        'team_id' => '',
        'category_id' => '',
        'is_active' => true,
    ];

    /** Texto del buscador de equipo; al elegir uno muestra su nombre. */
    public string $teamSearch = '';

    public function mount(): void
    {
        $this->authorize('viewAny', SeasonTeam::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSeasonFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    /** Al cambiar de temporada en el modal se reinicia el equipo elegido. */
    public function updatedFormSeasonId(): void
    {
        $this->clearTeam();
    }

    /** Si se vuelve a escribir, el equipo elegido deja de valer. */
    public function updatedTeamSearch(): void
    {
        $this->form['team_id'] = '';
    }

    public function seasonOptions(): Collection
    {
        return app(SeasonTeamService::class)->seasonsFor(auth()->user());
    }

    /** Max. 10 equipos elegibles para la temporada elegida que coinciden con lo escrito. */
    public function teamResults(): Collection
    {
        $season = $this->formSeason();

        if (! $season || $this->form['team_id'] !== '') {
            return collect();
        }

        return app(SeasonTeamService::class)->searchTeams(auth()->user(), $season, $this->teamSearch);
    }

    public function selectTeam(int $id): void
    {
        $season = $this->formSeason();
        abort_unless($season !== null, 422);

        $team = app(SeasonTeamService::class)->findEligibleTeam(auth()->user(), $season, $id);
        $this->form['team_id'] = $team->id;
        $this->teamSearch = $team->name;
    }

    public function clearTeam(): void
    {
        $this->form['team_id'] = '';
        $this->teamSearch = '';
    }

    private function formSeason(): ?Season
    {
        return ctype_digit((string) $this->form['season_id'])
            ? Season::with('gamesEdition')->find($this->form['season_id'])
            : null;
    }

    public function categoryOptions(): Collection
    {
        return Category::where('is_active', true)
            ->when($this->form['category_id'] !== '', fn ($q) => $q->orWhere('id', $this->form['category_id']))
            ->orderBy('name')
            ->get(['id', 'name', 'gender']);
    }

    public function create(): void
    {
        $this->authorize('create', SeasonTeam::class);

        $this->resetForm();

        if (ctype_digit($this->seasonFilter)) {
            $this->form['season_id'] = (int) $this->seasonFilter;
        }

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $seasonTeam = $this->findVisibleSeasonTeam($id);
        $this->authorize('update', $seasonTeam);

        $this->editingId = $seasonTeam->id;
        $this->form = [
            'season_id' => $seasonTeam->season_id,
            'team_id' => $seasonTeam->team_id,
            'category_id' => $seasonTeam->category_id ?? '',
            'is_active' => $seasonTeam->is_active,
        ];
        $this->showModal = true;
    }

    public function save(SeasonTeamService $service): void
    {
        $data = $this->validate($this->rules())['form'];

        foreach (['category_id'] as $field) {
            $data[$field] = in_array($data[$field], ['', null], true) ? null : (int) $data[$field];
        }

        if ($this->editingId) {
            $seasonTeam = $this->findVisibleSeasonTeam($this->editingId);
            $this->authorize('update', $seasonTeam);
            $service->update($seasonTeam, $data, auth()->user());
        } else {
            $this->authorize('create', SeasonTeam::class);
            $service->register($data, auth()->user());
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Inscripción guardada correctamente.');
    }

    public function delete(int $id, SeasonTeamService $service): void
    {
        $seasonTeam = $this->findVisibleSeasonTeam($id);
        $this->authorize('delete', $seasonTeam);

        $result = $service->delete($seasonTeam);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Inscripción retirada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    /** Inscripcion en edicion para la cabecera del modal (acotada al alcance). */
    public function editingSeasonTeam(): ?SeasonTeam
    {
        return $this->editingId ? $this->findVisibleSeasonTeam($this->editingId)->load(['team:id,name', 'season:id,name']) : null;
    }

    public function render(SeasonTeamService $service)
    {
        $user = auth()->user();

        $query = $service->scopeVisibleTo(SeasonTeam::query(), $user)
            // Alias para poder ordenar por nombre de equipo sin JOIN.
            ->addSelect(['season_team.*', 'team_name' => Team::select('name')->whereColumn('teams.id', 'season_team.team_id')])
            ->when($this->search !== '', fn ($q) => $q->whereHas('team', fn ($t) => $t->where('name', 'like', "%{$this->search}%")))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->where('season_id', (int) $this->seasonFilter))
            ->when(ctype_digit($this->categoryFilter), fn ($q) => $q->where('category_id', (int) $this->categoryFilter))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('roster')
            ->with([
                'team:id,name,logo_path',
                'season:id,name,status,games_edition_id,sport_id', 'season.gamesEdition:id,name,organizer_id', 'season.sport:id,name',
                'category:id,name,gender',
            ])
            ->orderBy($this->sortColumn(), $this->sortOrder());

        return view('livewire.season-teams.index', [
            'seasonTeams' => $query->paginate($this->perPageLimit()),
            // Solo temporadas con alguna inscripcion visible: el filtro no
            // debe revelar temporadas de olimpiadas ajenas (el admin ve todas).
            'filterSeasons' => Season::with('gamesEdition:id,name')
                ->unless($user->hasRole('admin'), fn ($q) => $q->whereHas('seasonTeams', fn ($st) => $service->scopeVisibleTo($st, $user)))
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'games_edition_id']),
            'filterCategories' => Category::orderBy('name')->get(['id', 'name', 'gender']),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['team_name', 'roster_count', 'is_active', 'created_at'];
    }

    /**
     * Ids que llegan del navegador se buscan acotados al alcance del
     * usuario (scopeVisibleTo): una inscripcion de otra olimpiada/equipo da
     * 404 antes de llegar a la Policy.
     */
    private function findVisibleSeasonTeam(int $id): SeasonTeam
    {
        return app(SeasonTeamService::class)
            ->scopeVisibleTo(SeasonTeam::query(), auth()->user())
            ->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'season_id' => '',
            'team_id' => '',
            'category_id' => '',
            'is_active' => true,
        ];
        $this->teamSearch = '';
        $this->resetErrorBag();
    }

    /**
     * Las reglas de negocio (quien inscribe que equipo en que temporada)
     * viven en SeasonTeamService; aqui solo formato y existencia.
     */
    private function rules(): array
    {
        return [
            'form.season_id' => ['required', 'integer', Rule::exists('seasons', 'id')],
            'form.team_id' => ['required', 'integer', Rule::exists('teams', 'id')],
            'form.category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'form.is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.season_id' => 'temporada',
            'form.team_id' => 'equipo',
            'form.category_id' => 'categoría',
        ];
    }
}
