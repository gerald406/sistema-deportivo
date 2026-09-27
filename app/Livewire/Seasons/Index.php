<?php

declare(strict_types=1);

namespace App\Livewire\Seasons;

use App\Enums\SeasonStatus;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\Sport;
use App\Models\Tournament;
use App\Services\SeasonService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Temporadas')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    public string $tournamentFilter = '';

    public string $sportFilter = '';

    /** all | draft | active | closed */
    public string $seasonStatusFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'start_date';

    public string $sortDirection = 'desc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'tournament_id' => '',
        'sport_id' => '',
        'games_edition_id' => '',
        'name' => '',
        'start_date' => '',
        'end_date' => '',
        'status' => 'draft',
        'is_active' => true,
    ];

    /**
     * Sub-formulario de ScoringConfig (varias fases, minimo una). Cada
     * item: id (null si es nueva), phase_name, pts_win, pts_draw,
     * pts_loss, pts_walkover.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $phases = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Season::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTournamentFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSportFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSeasonStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function statusOptions(): array
    {
        return SeasonStatus::cases();
    }

    /** Torneos para el selector del formulario: admin todos, organizador los suyos. */
    public function tournamentOptions(): Collection
    {
        return app(SeasonService::class)->tournamentsFor(auth()->user());
    }

    /** Todos los torneos, para el filtro del listado (cualquier rol puede ver temporadas). */
    public function tournamentFilterOptions(): Collection
    {
        return Tournament::orderBy('name')->get(['id', 'name']);
    }

    public function sportOptions(): Collection
    {
        return Sport::where('is_active', true)
            ->when($this->form['sport_id'] !== '', fn ($q) => $q->orWhere('id', $this->form['sport_id']))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function editionOptions(): Collection
    {
        return GamesEdition::where('is_active', true)
            ->when($this->form['games_edition_id'] !== '', fn ($q) => $q->orWhere('id', $this->form['games_edition_id']))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(): void
    {
        $this->authorize('create', Season::class);

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $season = Season::with('scoringConfigs')->findOrFail($id);
        $this->authorize('update', $season);

        $this->editingId = $season->id;
        $this->form = [
            'tournament_id' => $season->tournament_id,
            'sport_id' => $season->sport_id,
            'games_edition_id' => $season->games_edition_id ?? '',
            'name' => $season->name,
            'start_date' => $season->start_date?->format('Y-m-d') ?? '',
            'end_date' => $season->end_date?->format('Y-m-d') ?? '',
            'status' => $season->status->value,
            'is_active' => $season->is_active,
        ];
        $this->phases = $season->scoringConfigs
            ->sortBy('id')
            ->map(fn ($c) => [
                'id' => $c->id,
                'phase_name' => $c->phase_name,
                'pts_win' => $c->pts_win,
                'pts_draw' => $c->pts_draw,
                'pts_loss' => $c->pts_loss,
                'pts_walkover' => $c->pts_walkover,
            ])->values()->all();

        if ($this->phases === []) {
            $this->addPhase();
        }

        $this->showModal = true;
    }

    public function addPhase(): void
    {
        $this->phases[] = [
            'id' => null,
            'phase_name' => $this->phases === [] ? 'Fase Regular' : '',
            'pts_win' => 3,
            'pts_draw' => 1,
            'pts_loss' => 0,
            'pts_walkover' => -1,
        ];
    }

    public function removePhase(int $index): void
    {
        if (count($this->phases) <= 1) {
            return;
        }

        unset($this->phases[$index]);
        $this->phases = array_values($this->phases);
    }

    public function save(SeasonService $service): void
    {
        $validated = $this->validate($this->rules());
        $data = $validated['form'];
        $actor = auth()->user();

        foreach (['games_edition_id', 'start_date', 'end_date'] as $field) {
            $data[$field] = in_array($data[$field], ['', null], true) ? null : $data[$field];
        }

        if ($this->editingId) {
            $season = Season::findOrFail($this->editingId);
            $this->authorize('update', $season);
            $service->update($season, $data, $validated['phases'], $actor);
        } else {
            $this->authorize('create', Season::class);
            $service->register($data, $validated['phases'], $actor);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Temporada guardada correctamente.');
    }

    public function delete(int $id, SeasonService $service): void
    {
        $season = Season::findOrFail($id);
        $this->authorize('delete', $season);

        $result = $service->delete($season);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Temporada eliminada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = Season::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when(ctype_digit($this->tournamentFilter), fn ($q) => $q->where('tournament_id', (int) $this->tournamentFilter))
            ->when(ctype_digit($this->sportFilter), fn ($q) => $q->where('sport_id', (int) $this->sportFilter))
            ->when(SeasonStatus::tryFrom($this->seasonStatusFilter), fn ($q, SeasonStatus $s) => $q->where('status', $s->value))
            ->withCount(['seasonTeams', 'scoringConfigs'])
            ->with(['tournament:id,name,organizer_id', 'sport:id,name', 'gamesEdition:id,name'])
            ->orderBy($this->sortColumn(), $this->sortOrder());

        return view('livewire.seasons.index', [
            'seasons' => $query->paginate($this->perPageLimit()),
            'filterSports' => Sport::orderBy('name')->get(['id', 'name']),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['start_date', 'name', 'end_date', 'status', 'season_teams_count', 'is_active'];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'tournament_id' => '',
            'sport_id' => '',
            'games_edition_id' => '',
            'name' => '',
            'start_date' => '',
            'end_date' => '',
            'status' => 'draft',
            'is_active' => true,
        ];
        $this->phases = [];
        $this->addPhase();
        $this->resetErrorBag();
    }

    private function rules(): array
    {
        $hasStart = ! in_array($this->form['start_date'], ['', null], true);
        $points = ['required', 'integer', 'between:-99,99'];

        return [
            'form.tournament_id' => ['required', 'integer', Rule::exists('tournaments', 'id')],
            'form.sport_id' => ['required', 'integer', Rule::exists('sports', 'id')],
            'form.games_edition_id' => ['nullable', 'integer', Rule::exists('games_editions', 'id')],
            'form.name' => [
                'required', 'string', 'max:100',
                Rule::unique('seasons', 'name')
                    ->where('tournament_id', (int) $this->form['tournament_id'])
                    ->ignore($this->editingId),
            ],
            'form.start_date' => ['nullable', 'date'],
            'form.end_date' => array_filter(['nullable', 'date', $hasStart ? 'after_or_equal:form.start_date' : null]),
            'form.status' => ['required', Rule::enum(SeasonStatus::class)],
            'form.is_active' => ['boolean'],
            'phases' => ['required', 'array', 'min:1'],
            'phases.*.id' => ['nullable', 'integer'],
            'phases.*.phase_name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'phases.*.pts_win' => $points,
            'phases.*.pts_draw' => $points,
            'phases.*.pts_loss' => $points,
            'phases.*.pts_walkover' => $points,
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.tournament_id' => 'torneo',
            'form.sport_id' => 'deporte',
            'form.games_edition_id' => 'edición',
            'form.name' => 'nombre',
            'form.start_date' => 'fecha de inicio',
            'form.end_date' => 'fecha de fin',
            'phases.*.phase_name' => 'nombre de fase',
            'phases.*.pts_win' => 'puntos por victoria',
            'phases.*.pts_draw' => 'puntos por empate',
            'phases.*.pts_loss' => 'puntos por derrota',
            'phases.*.pts_walkover' => 'puntos por walkover',
        ];
    }
}
