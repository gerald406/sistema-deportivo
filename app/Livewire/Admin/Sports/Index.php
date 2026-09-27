<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Sports;

use App\Enums\BetterDirection;
use App\Enums\SportFormatType;
use App\Enums\UnitOfMeasure;
use App\Livewire\Concerns\Sortable;
use App\Models\Discipline;
use App\Models\Sport;
use App\Services\SportCatalogService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Deportes y disciplinas')]
class Index extends Component
{
    use Sortable, WithPagination;

    public string $search = '';

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    // --- Modal de Sport ---
    public bool $showSportModal = false;

    public ?int $editingSportId = null;

    public array $sportForm = [
        'name' => '',
        'slug' => '',
        'format_type' => 'head_to_head',
        'is_active' => true,
    ];

    // --- Modal de Discipline (anidado: se abre "sobre" el listado de un Sport) ---
    public bool $showDisciplinesModal = false;

    public ?int $managingSportId = null;

    public ?int $editingDisciplineId = null;

    public array $disciplineForm = [
        'name' => '',
        'format_type' => '',
        'unit_of_measure' => 'points',
        'better_direction' => 'asc',
        'is_active' => true,
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function formatTypeOptions(): array
    {
        return SportFormatType::cases();
    }

    public function unitOfMeasureOptions(): array
    {
        return UnitOfMeasure::cases();
    }

    public function betterDirectionOptions(): array
    {
        return BetterDirection::cases();
    }

    public function managingSport(): ?Sport
    {
        return $this->managingSportId ? Sport::find($this->managingSportId) : null;
    }

    // ----- Sport -----

    public function createSport(): void
    {
        $this->resetSportForm();
        $this->showSportModal = true;
    }

    public function editSport(int $id): void
    {
        $sport = Sport::findOrFail($id);
        $this->editingSportId = $sport->id;
        $this->sportForm = [
            'name' => $sport->name,
            'slug' => $sport->slug,
            'format_type' => $sport->format_type->value,
            'is_active' => $sport->is_active,
        ];
        $this->showSportModal = true;
    }

    public function saveSport(SportCatalogService $service): void
    {
        $data = $this->validate([
            'sportForm.name' => ['required', 'string', 'max:100'],
            'sportForm.slug' => [
                'nullable', 'string', 'max:100',
                Rule::unique('sports', 'slug')->ignore($this->editingSportId),
            ],
            'sportForm.format_type' => ['required', Rule::in(array_column(SportFormatType::cases(), 'value'))],
            'sportForm.is_active' => ['boolean'],
        ])['sportForm'];

        if ($this->editingSportId) {
            $service->updateSport(Sport::findOrFail($this->editingSportId), $data);
        } else {
            $service->registerSport($data);
        }

        $this->showSportModal = false;
        $this->resetSportForm();
        $this->dispatch('toast', type: 'success', message: 'Deporte guardado correctamente.');
    }

    public function deleteSport(int $id, SportCatalogService $service): void
    {
        $sport = Sport::findOrFail($id);

        if (! $service->deleteSport($sport)) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar: el deporte tiene disciplinas o temporadas registradas.');

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Deporte eliminado.');
    }

    public function closeSportModal(): void
    {
        $this->showSportModal = false;
        $this->resetSportForm();
    }

    // ----- Discipline (gestion anidada por deporte) -----

    public function manageDisciplines(int $sportId): void
    {
        $this->managingSportId = $sportId;
        $this->resetDisciplineForm();
        $this->showDisciplinesModal = true;
    }

    public function createDiscipline(): void
    {
        $this->resetDisciplineForm();
    }

    public function editDiscipline(int $id): void
    {
        $discipline = Discipline::findOrFail($id);
        $this->editingDisciplineId = $discipline->id;
        $this->disciplineForm = [
            'name' => $discipline->name,
            'format_type' => $discipline->format_type?->value ?? '',
            'unit_of_measure' => $discipline->unit_of_measure->value,
            'better_direction' => $discipline->better_direction->value,
            'is_active' => $discipline->is_active,
        ];
    }

    public function saveDiscipline(SportCatalogService $service): void
    {
        $sport = $this->managingSport();
        abort_unless($sport, 404);

        $data = $this->validate([
            'disciplineForm.name' => [
                'required', 'string', 'max:100',
                Rule::unique('disciplines', 'name')
                    ->where('sport_id', $sport->id)
                    ->ignore($this->editingDisciplineId),
            ],
            'disciplineForm.format_type' => ['nullable', Rule::in(array_column(SportFormatType::cases(), 'value'))],
            'disciplineForm.unit_of_measure' => ['required', Rule::in(array_column(UnitOfMeasure::cases(), 'value'))],
            'disciplineForm.better_direction' => ['required', Rule::in(array_column(BetterDirection::cases(), 'value'))],
            'disciplineForm.is_active' => ['boolean'],
        ])['disciplineForm'];

        $data['format_type'] = $data['format_type'] !== '' ? $data['format_type'] : null;

        if ($this->editingDisciplineId) {
            $service->updateDiscipline(Discipline::findOrFail($this->editingDisciplineId), $data);
        } else {
            $service->registerDiscipline($sport, $data);
        }

        $this->resetDisciplineForm();
        $this->dispatch('toast', type: 'success', message: 'Disciplina guardada correctamente.');
    }

    public function deleteDiscipline(int $id, SportCatalogService $service): void
    {
        $discipline = Discipline::findOrFail($id);

        if (! $service->deleteDiscipline($discipline)) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar: la disciplina tiene partidos registrados.');

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Disciplina eliminada.');
    }

    public function closeDisciplinesModal(): void
    {
        $this->showDisciplinesModal = false;
        $this->managingSportId = null;
        $this->resetDisciplineForm();
    }

    public function render()
    {
        $sports = Sport::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withCount('disciplines')
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->paginate(15);

        $disciplines = $this->managingSportId
            ? Discipline::where('sport_id', $this->managingSportId)->orderBy('name')->get()
            : collect();

        return view('livewire.admin.sports.index', [
            'sports' => $sports,
            'disciplines' => $disciplines,
        ]);
    }

    protected function sortableFields(): array
    {
        return ['name', 'disciplines_count', 'is_active'];
    }

    private function resetSportForm(): void
    {
        $this->editingSportId = null;
        $this->sportForm = [
            'name' => '',
            'slug' => '',
            'format_type' => 'head_to_head',
            'is_active' => true,
        ];
        $this->resetErrorBag();
    }

    private function resetDisciplineForm(): void
    {
        $this->editingDisciplineId = null;
        $this->disciplineForm = [
            'name' => '',
            'format_type' => '',
            'unit_of_measure' => 'points',
            'better_direction' => 'asc',
            'is_active' => true,
        ];
        $this->resetErrorBag();
    }
}
