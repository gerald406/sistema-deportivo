<?php

declare(strict_types=1);

namespace App\Livewire\Matchdays;

use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Matchday;
use App\Services\MatchdayService;
use App\Support\SeasonAccess;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Jornadas: nacen con la llave en /fases (una por ronda, BracketService).
 * Aqui solo se ajustan fechas, observaciones y cierre; no hay alta manual
 * (D27), porque una jornada sin partidos de la llave quedaria vacia.
 */
#[Layout('layouts.admin')]
#[Title('Jornadas')]
class Index extends Component
{
    use ExportsExcel, LimitsPerPage, Sortable, WithPagination;

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

    /** Solo edita: las jornadas nacen con la llave (BracketService), no se crean aqui. */
    public function save(MatchdayService $service): void
    {
        abort_unless($this->editingId !== null, 404);

        $matchday = Matchday::findOrFail($this->editingId);
        $this->authorize('update', $matchday);

        $data = $this->validate($this->rules())['form'];

        foreach (['start_date', 'end_date', 'observations'] as $field) {
            $data[$field] = trim((string) $data[$field]) === '' ? null : $data[$field];
        }

        $service->update($matchday, $data, auth()->user());

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

        $query = $this->listingQuery();

        return view('livewire.matchdays.index', [
            'matchdays' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => SeasonAccess::manageableSeasons($user, includeClosed: true),
        ]);
    }

    /** Listado filtrado y ordenado: lo comparten render() y la exportacion a Excel. */
    private function listingQuery(): Builder
    {
        $user = auth()->user();

        return Matchday::query()
            // El organizador solo ve jornadas de sus olimpiadas.
            ->unless($user->hasRole('admin'), fn ($q) => $q->whereHas('season.gamesEdition', fn ($t) => $t->where('organizer_id', $user->id)))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->where('season_id', (int) $this->seasonFilter))
            ->when($this->completionFilter === 'pending', fn ($q) => $q->where('is_completed', false))
            ->when($this->completionFilter === 'completed', fn ($q) => $q->where('is_completed', true))
            ->withCount(['matches', 'matches as pending_matches_count' => fn ($q) => $q->where('status', 'pending')])
            ->with(['season:id,name,status,games_edition_id,sport_id', 'season.gamesEdition:id,name,organizer_id', 'season.sport:id,name'])
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('id');
    }

    protected function authorizeExport(): void
    {
        $this->authorize('viewAny', Matchday::class);
    }

    protected function exportTitle(): string
    {
        return 'Jornadas';
    }

    protected function exportRows(): iterable
    {
        return $this->listingQuery()->get();
    }

    protected function exportColumns(): array
    {
        return [
            'Jornada' => fn (Matchday $m) => $m->name,
            'Observaciones' => fn (Matchday $m) => $m->observations,
            'Competencia' => fn (Matchday $m) => $m->season->name,
            'Olimpiada' => fn (Matchday $m) => $m->season->gamesEdition->name,
            'Deporte' => fn (Matchday $m) => $m->season->sport->name,
            'Inicio' => fn (Matchday $m) => $m->start_date,
            'Fin' => fn (Matchday $m) => $m->end_date,
            'Partidos' => fn (Matchday $m) => $m->matches_count,
            'Por jugar' => fn (Matchday $m) => $m->pending_matches_count,
            'Estado' => fn (Matchday $m) => $m->is_completed ? 'Completada' : 'Por jugar',
        ];
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

        // Competencia y nombre no se editan: los fija la llave.
        return [
            'form.start_date' => ['nullable', 'date'],
            'form.end_date' => array_filter(['nullable', 'date', $hasStart ? 'after_or_equal:form.start_date' : null]),
            'form.is_completed' => ['boolean'],
            'form.observations' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.start_date' => 'fecha de inicio',
            'form.end_date' => 'fecha de fin',
        ];
    }
}
