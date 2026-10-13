<?php

declare(strict_types=1);

namespace App\Livewire\Teams;

use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Team;
use App\Services\TeamRegistrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Catalogo de delegaciones (Team). Sus delegados NO se asignan aqui: viven
 * en la inscripcion de la delegacion en la olimpiada (/delegaciones-inscritas,
 * games_edition_teams). El delegado no entra a esta pantalla (TeamPolicy).
 */
#[Layout('layouts.admin')]
#[Title('Delegaciones')]
class Index extends Component
{
    use ExportsExcel, LimitsPerPage, Sortable, WithFileUploads, WithPagination;

    public string $search = '';

    /** all | active | inactive */
    public string $statusFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'name' => '',
        'is_active' => true,
    ];

    /** Logo nuevo pendiente de subir (Livewire\WithFileUploads). */
    public $newLogo = null;

    /** URL del logo actual, para mostrarlo en el modal al editar. */
    public ?string $existingLogoUrl = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Team::class);
    }

    public function updatingSearch(): void
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

    public function create(): void
    {
        $this->authorize('create', Team::class);

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $team = $this->findManagedTeam($id);
        $this->authorize('update', $team);

        $this->editingId = $team->id;
        $this->form = [
            'name' => $team->name,
            'is_active' => $team->is_active,
        ];
        $this->existingLogoUrl = $team->logo_path ? Storage::disk('public')->url($team->logo_path) : null;
        $this->showModal = true;
    }

    public function save(TeamRegistrationService $service): void
    {
        $data = $this->validate($this->rules())['form'];
        $actor = auth()->user();

        if ($this->editingId) {
            $team = $this->findManagedTeam($this->editingId);
            $this->authorize('update', $team);

            if ($this->newLogo) {
                $this->deleteLogoIfExists($team->logo_path);
                $data['logo_path'] = $this->newLogo->store('teams', 'public');
            }

            $service->update($team, $data, $actor);
        } else {
            $this->authorize('create', Team::class);

            if ($this->newLogo) {
                $data['logo_path'] = $this->newLogo->store('teams', 'public');
            }

            $service->register($data, $actor);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Delegación guardada correctamente.');
    }

    public function delete(int $id, TeamRegistrationService $service): void
    {
        $team = $this->findManagedTeam($id);
        $this->authorize('delete', $team);

        $result = $service->delete($team);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Delegación eliminada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.teams.index', [
            'teams' => $this->listingQuery()->paginate($this->perPageLimit()),
        ]);
    }

    /** Listado filtrado y ordenado: lo comparten render() y la exportacion a Excel. */
    private function listingQuery(): Builder
    {
        return app(TeamRegistrationService::class)->scopeManagedBy(Team::query(), auth()->user())
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount(['seasonTeams', 'editionTeams'])
            ->orderBy($this->sortColumn(), $this->sortOrder());
    }

    protected function authorizeExport(): void
    {
        $this->authorize('viewAny', Team::class);
    }

    protected function exportTitle(): string
    {
        return 'Delegaciones';
    }

    protected function exportRows(): iterable
    {
        return $this->listingQuery()->get();
    }

    protected function exportColumns(): array
    {
        return [
            'Nombre' => fn (Team $t) => $t->name,
            'Olimpiadas' => fn (Team $t) => $t->edition_teams_count,
            'Competencias' => fn (Team $t) => $t->season_teams_count,
            'Estado' => fn (Team $t) => $t->is_active ? 'Activa' : 'Inactiva',
            'Creado' => fn (Team $t) => $t->created_at,
        ];
    }

    protected function sortableFields(): array
    {
        return ['name', 'edition_teams_count', 'season_teams_count', 'is_active', 'created_at'];
    }

    /**
     * Ids que llegan del navegador se buscan acotados al alcance del
     * usuario (scopeManagedBy): un organizador que mande el id de una
     * delegacion ajena recibe 404 antes de llegar a la Policy.
     */
    private function findManagedTeam(int $id): Team
    {
        return app(TeamRegistrationService::class)
            ->scopeManagedBy(Team::query(), auth()->user())
            ->findOrFail($id);
    }

    private function deleteLogoIfExists(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'is_active' => true,
        ];
        $this->newLogo = null;
        $this->existingLogoUrl = null;
        $this->resetErrorBag();
    }

    private function rules(): array
    {
        return [
            'form.name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('teams', 'name')->ignore($this->editingId),
            ],
            'form.is_active' => ['boolean'],
            'newLogo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => 'nombre',
            'newLogo' => 'logo',
        ];
    }
}
