<?php

declare(strict_types=1);

namespace App\Livewire\Tournaments;

use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Tournament;
use App\Services\TournamentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Torneos')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithFileUploads, WithPagination;

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
        'organizer_id' => '',
        'is_active' => true,
    ];

    /** Logo nuevo pendiente de subir (Livewire\WithFileUploads). */
    public $newLogo = null;

    /** URL del logo actual, para mostrarlo en el modal al editar. */
    public ?string $existingLogoUrl = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Tournament::class);
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

    public function organizers(): Collection
    {
        return app(TournamentService::class)->organizerCandidates();
    }

    public function isAdmin(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public function create(): void
    {
        $this->authorize('create', Tournament::class);

        $this->resetForm();
        $this->form['organizer_id'] = auth()->id();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $tournament = Tournament::findOrFail($id);
        $this->authorize('update', $tournament);

        $this->editingId = $tournament->id;
        $this->form = [
            'name' => $tournament->name,
            'organizer_id' => $tournament->organizer_id,
            'is_active' => $tournament->is_active,
        ];
        $this->existingLogoUrl = $tournament->logo ? Storage::disk('public')->url($tournament->logo) : null;
        $this->showModal = true;
    }

    public function save(TournamentService $service): void
    {
        $data = $this->validate($this->rules())['form'];
        $actor = auth()->user();

        if ($this->editingId) {
            $tournament = Tournament::findOrFail($this->editingId);
            $this->authorize('update', $tournament);

            if ($this->newLogo) {
                $previousLogo = $tournament->logo;
                $data['logo'] = $this->newLogo->store('tournaments', 'public');
            }

            $service->update($tournament, $data, $actor);

            // Se borra el logo anterior solo despues de guardar: si el
            // Service rechaza el cambio, el torneo conserva su logo.
            if (isset($previousLogo)) {
                $this->deleteLogoIfExists($previousLogo);
            }
        } else {
            $this->authorize('create', Tournament::class);

            if ($this->newLogo) {
                $data['logo'] = $this->newLogo->store('tournaments', 'public');
            }

            $service->register($data, $actor);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Torneo guardado correctamente.');
    }

    public function delete(int $id, TournamentService $service): void
    {
        $tournament = Tournament::findOrFail($id);
        $this->authorize('delete', $tournament);

        $result = $service->delete($tournament);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Torneo eliminado.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = Tournament::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('seasons')
            ->with('organizer:id,name')
            ->orderBy($this->sortColumn(), $this->sortOrder());

        return view('livewire.tournaments.index', [
            'tournaments' => $query->paginate($this->perPageLimit()),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['name', 'seasons_count', 'is_active', 'created_at'];
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
            'organizer_id' => '',
            'is_active' => true,
        ];
        $this->newLogo = null;
        $this->existingLogoUrl = null;
        $this->resetErrorBag();
    }

    /**
     * organizer_id solo se valida aqui como entero; que sea un
     * organizador/admin activo (y que un organizador no pueda cambiarlo)
     * lo decide TournamentService::resolveOrganizerId().
     */
    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:150'],
            'form.organizer_id' => ['nullable', 'integer'],
            'form.is_active' => ['boolean'],
            'newLogo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
