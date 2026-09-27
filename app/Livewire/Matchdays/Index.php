<?php

declare(strict_types=1);

namespace App\Livewire\Matchdays;

use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Matchday;
use App\Services\MatchdayService;
use App\Support\SeasonAccess;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Jornadas')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    public string $seasonFilter = '';

    /** all | pending | completed */
    public string $completionFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'start_date';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'season_id' => '',
        'name' => '',
        'start_date' => '',
        'end_date' => '',
        'is_completed' => false,
        'observations' => '',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Matchday::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSeasonFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCompletionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    /** Temporadas (no cerradas) en las que el usuario puede crear jornadas. */
    public function seasonOptions(): Collection
    {
        return SeasonAccess::manageableSeasons(auth()->user());
    }

    public function create(): void
    {
        $this->authorize('create', Matchday::class);

        $this->resetForm();

        if (ctype_digit($this->seasonFilter) && $this->seasonOptions()->contains('id', (int) $this->seasonFilter)) {
            $this->form['season_id'] = (int) $this->seasonFilter;
            $this->form['name'] = 'Fecha ' . (Matchday::where('season_id', (int) $this->seasonFilter)->count() + 1);
        }

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $matchday = Matchday::findOrFail($id);
        $this->authorize('update', $matchday);

        $this->editingId = $matchday->id;
        $this->form = [
            'season_id' => $matchday->season_id,
            'name' => $matchday->name,
            'start_date' => $matchday->start_date?->format('Y-m-d') ?? '',
            'end_date' => $matchday->end_date?->format('Y-m-d') ?? '',
            'is_completed' => $matchday->is_completed,
            'observations' => $matchday->observations ?? '',
        ];
        $this->showModal = true;
    }

    public function save(MatchdayService $service): void
    {
        $data = $this->validate($this->rules())['form'];

        foreach (['start_date', 'end_date', 'observations'] as $field) {
            $data[$field] = trim((string) $data[$field]) === '' ? null : $data[$field];
        }

        if ($this->editingId) {
            $matchday = Matchday::findOrFail($this->editingId);
            $this->authorize('update', $matchday);
            $service->update($matchday, $data, auth()->user());
        } else {
            $this->authorize('create', Matchday::class);
            $service->register($data, auth()->user());
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Jornada guardada correctamente.');
    }

    public function delete(int $id, MatchdayService $service): void
    {
        $matchday = Matchday::findOrFail($id);
        $this->authorize('delete', $matchday);

        $result = $service->delete($matchday);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Jornada eliminada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $user = auth()->user();

        $query = Matchday::query()
            // El organizador solo ve jornadas de sus torneos.
            ->unless($user->hasRole('admin'), fn ($q) => $q->whereHas('season.tournament', fn ($t) => $t->where('organizer_id', $user->id)))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->where('season_id', (int) $this->seasonFilter))
            ->when($this->completionFilter === 'pending', fn ($q) => $q->where('is_completed', false))
            ->when($this->completionFilter === 'completed', fn ($q) => $q->where('is_completed', true))
            ->withCount(['matches', 'matches as pending_matches_count' => fn ($q) => $q->where('status', 'pending')])
            ->with(['season:id,name,status,tournament_id,sport_id', 'season.tournament:id,name,organizer_id', 'season.sport:id,name'])
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('id');

        return view('livewire.matchdays.index', [
            'matchdays' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => SeasonAccess::manageableSeasons($user, includeClosed: true),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['start_date', 'name', 'end_date', 'matches_count', 'is_completed'];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'season_id' => '',
            'name' => '',
            'start_date' => '',
            'end_date' => '',
            'is_completed' => false,
            'observations' => '',
        ];
        $this->resetErrorBag();
    }

    private function rules(): array
    {
        $hasStart = trim((string) $this->form['start_date']) !== '';

        return [
            'form.season_id' => ['required', 'integer', Rule::exists('seasons', 'id')],
            'form.name' => [
                'required', 'string', 'max:50',
                Rule::unique('matchdays', 'name')
                    ->where('season_id', (int) $this->form['season_id'])
                    ->ignore($this->editingId),
            ],
            'form.start_date' => ['nullable', 'date'],
            'form.end_date' => array_filter(['nullable', 'date', $hasStart ? 'after_or_equal:form.start_date' : null]),
            'form.is_completed' => ['boolean'],
            'form.observations' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.season_id' => 'temporada',
            'form.name' => 'nombre',
            'form.start_date' => 'fecha de inicio',
            'form.end_date' => 'fecha de fin',
        ];
    }
}
