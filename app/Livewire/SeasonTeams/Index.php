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
        'delegate_id' => '',
        'is_active' => true,
    ];

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
        $this->form['team_id'] = '';
    }

    public function seasonOptions(): Collection
    {
        return app(SeasonTeamService::class)->seasonsFor(auth()->user());
    }

    public function teamOptions(): Collection
    {
        $season = ctype_digit((string) $this->form['season_id']) ? Season::with('tournament')->find($this->form['season_id']) : null;

        return $season ? app(SeasonTeamService::class)->teamsFor(auth()->user(), $season) : collect();
    }

    public function categoryOptions(): Collection
    {
        return Category::where('is_active', true)
            ->when($this->form['category_id'] !== '', fn ($q) => $q->orWhere('id', $this->form['category_id']))
            ->orderBy('name')
            ->get(['id', 'name', 'gender']);
    }

    public function delegateOptions(): Collection
    {
        return User::role('delegado')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /** Solo admin/organizador del torneo eligen delegado de temporada. */
    public function canChooseDelegate(): bool
    {
        $user = auth()->user();

        if ($this->editingId) {
            return $user->can('update', SeasonTeam::findOrFail($this->editingId));
        }

        $season = ctype_digit((string) $this->form['season_id']) ? Season::with('tournament')->find($this->form['season_id']) : null;

        return $season ? app(SeasonTeamService::class)->isManager($user, $season) : $user->hasRole('admin');
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
        $seasonTeam = SeasonTeam::findOrFail($id);
        $this->authorize('update', $seasonTeam);

        $this->editingId = $seasonTeam->id;
        $this->form = [
            'season_id' => $seasonTeam->season_id,
            'team_id' => $seasonTeam->team_id,
            'category_id' => $seasonTeam->category_id ?? '',
            'delegate_id' => $seasonTeam->delegate_id ?? '',
            'is_active' => $seasonTeam->is_active,
        ];
        $this->showModal = true;
    }

    public function save(SeasonTeamService $service): void
    {
        $data = $this->validate($this->rules())['form'];

        foreach (['category_id', 'delegate_id'] as $field) {
            $data[$field] = in_array($data[$field], ['', null], true) ? null : (int) $data[$field];
        }

        if ($this->editingId) {
            $seasonTeam = SeasonTeam::findOrFail($this->editingId);
            $this->authorize('update', $seasonTeam);
            $service->update($seasonTeam, $data);
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
        $seasonTeam = SeasonTeam::findOrFail($id);
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

    public function render()
    {
        $query = SeasonTeam::query()
            // Alias para poder ordenar por nombre de equipo sin JOIN.
            ->addSelect(['season_team.*', 'team_name' => Team::select('name')->whereColumn('teams.id', 'season_team.team_id')])
            ->when($this->search !== '', fn ($q) => $q->whereHas('team', fn ($t) => $t->where('name', 'like', "%{$this->search}%")))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->where('season_id', (int) $this->seasonFilter))
            ->when(ctype_digit($this->categoryFilter), fn ($q) => $q->where('category_id', (int) $this->categoryFilter))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('roster')
            ->with([
                'team:id,name,logo_path,delegate_id', 'team.delegate:id,name',
                'season:id,name,status,tournament_id,sport_id', 'season.tournament:id,name,organizer_id', 'season.sport:id,name',
                'category:id,name,gender', 'delegate:id,name',
            ])
            ->orderBy($this->sortColumn(), $this->sortOrder());

        return view('livewire.season-teams.index', [
            'seasonTeams' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => Season::with('tournament:id,name')->orderByDesc('start_date')->get(['id', 'name', 'tournament_id']),
            'filterCategories' => Category::orderBy('name')->get(['id', 'name', 'gender']),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['team_name', 'roster_count', 'is_active', 'created_at'];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'season_id' => '',
            'team_id' => '',
            'category_id' => '',
            'delegate_id' => '',
            'is_active' => true,
        ];
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
            'form.delegate_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'form.is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.season_id' => 'temporada',
            'form.team_id' => 'equipo',
            'form.category_id' => 'categoría',
            'form.delegate_id' => 'delegado de temporada',
        ];
    }
}
