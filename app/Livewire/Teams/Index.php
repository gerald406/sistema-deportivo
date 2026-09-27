<?php

declare(strict_types=1);

namespace App\Livewire\Teams;

use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Team;
use App\Models\User;
use App\Services\DelegateAccountService;
use App\Services\TeamRegistrationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Equipos')]
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
        'is_active' => true,
    ];

    // --- Autocomplete de "Delegado responsable" (admin/organizador) ---
    /** Texto del buscador; al elegir un delegado muestra su nombre. */
    public string $delegateSearch = '';

    /** Delegado elegido. Viene del navegador: el Service revalida que sea rol delegado. */
    public ?int $selectedDelegateId = null;

    /** Logo nuevo pendiente de subir (Livewire\WithFileUploads). */
    public $newLogo = null;

    /** URL del logo actual, para mostrarlo en el modal al editar. */
    public ?string $existingLogoUrl = null;

    // --- Creación rápida de cuenta de delegado (solo admin) ---
    public bool $creatingDelegate = false;

    public string $newDelegateLastName = '';

    public string $newDelegateFirstName = '';

    public string $newDelegateEmail = '';

    public string $newDelegatePassword = '';

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

    /** Maximo 10 delegados que coinciden con lo escrito (nunca la lista completa). */
    public function delegateResults(): Collection
    {
        if ($this->selectedDelegateId || ! $this->canAssignDelegate()) {
            return collect();
        }

        return app(TeamRegistrationService::class)->searchDelegates($this->delegateSearch);
    }

    /** Si se vuelve a escribir, la seleccion anterior deja de valer. */
    public function updatedDelegateSearch(): void
    {
        $this->selectedDelegateId = null;
    }

    public function selectDelegate(int $id): void
    {
        abort_unless($this->canAssignDelegate(), 403);

        $delegate = User::role('delegado')->where('is_active', true)->findOrFail($id);
        $this->selectedDelegateId = $delegate->id;
        $this->delegateSearch = $delegate->name;
    }

    public function clearDelegate(): void
    {
        $this->selectedDelegateId = null;
        $this->delegateSearch = '';
    }

    public function isAdmin(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /** Admin u organizador eligen el delegado del equipo (el delegado no). */
    public function canAssignDelegate(): bool
    {
        return app(TeamRegistrationService::class)->canAssignDelegate(auth()->user());
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
        $this->selectedDelegateId = $team->delegate_id;
        $this->delegateSearch = $team->delegate?->name ?? '';
        $this->existingLogoUrl = $team->logo_path ? Storage::disk('public')->url($team->logo_path) : null;
        $this->showModal = true;
    }

    public function save(TeamRegistrationService $service): void
    {
        $validated = $this->validate($this->rules());
        $data = $validated['form'];
        $data['delegate_id'] = $this->selectedDelegateId ?? '';
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
        $this->dispatch('toast', type: 'success', message: 'Equipo guardado correctamente.');
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

        $this->dispatch('toast', type: 'success', message: 'Equipo eliminado.');
    }

    public function toggleCreatingDelegate(): void
    {
        $this->creatingDelegate = ! $this->creatingDelegate;
        $this->newDelegateLastName = '';
        $this->newDelegateFirstName = '';
        $this->newDelegateEmail = '';
        $this->newDelegatePassword = '';
        $this->resetErrorBag(['newDelegateLastName', 'newDelegateFirstName', 'newDelegateEmail', 'newDelegatePassword']);
    }

    public function saveNewDelegateAccount(DelegateAccountService $service): void
    {
        // Defensa en profundidad: este metodo es invocable directamente
        // aunque el boton este oculto en el Blade para no-admins.
        abort_unless($this->isAdmin(), 403);

        $data = $this->validate([
            'newDelegateFirstName' => ['required', 'string', 'max:100'],
            'newDelegateLastName' => ['required', 'string', 'max:100'],
            'newDelegateEmail' => ['required', 'email', 'unique:users,email'],
            'newDelegatePassword' => ['required', 'string', 'min:8'],
        ]);

        // users.name es un solo campo (asi lo genera Jetstream): se arman
        // nombres+apellidos aqui, igual que Player::fullName(), sin tener
        // que agregar columnas nuevas a la tabla users.
        $delegate = $service->createQuickAccount([
            'name' => trim("{$data['newDelegateFirstName']} {$data['newDelegateLastName']}"),
            'email' => $data['newDelegateEmail'],
            'password' => $data['newDelegatePassword'],
        ], auth()->user());

        $this->selectedDelegateId = $delegate->id;
        $this->delegateSearch = $delegate->name;
        $this->toggleCreatingDelegate();

        $this->dispatch('toast', type: 'success', message: 'Cuenta de delegado creada y asignada al equipo.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $service = app(TeamRegistrationService::class);

        $query = $service->scopeManagedBy(Team::query(), auth()->user())
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('seasonTeams')
            ->with('delegate:id,name')
            ->orderBy($this->sortColumn(), $this->sortOrder());

        return view('livewire.teams.index', [
            'teams' => $query->paginate($this->perPageLimit()),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['name', 'season_teams_count', 'is_active', 'created_at'];
    }

    /**
     * Ids que llegan del navegador se buscan acotados al alcance del
     * usuario (scopeManagedBy): un organizador que mande el id de un equipo
     * ajeno recibe 404 antes de llegar a la Policy.
     */
    private function findManagedTeam(int $id): Team
    {
        return app(TeamRegistrationService::class)
            ->scopeManagedBy(Team::query(), auth()->user())
            ->with('delegate:id,name')
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
        $this->delegateSearch = '';
        $this->selectedDelegateId = null;
        $this->newLogo = null;
        $this->existingLogoUrl = null;
        $this->creatingDelegate = false;
        $this->newDelegateLastName = '';
        $this->newDelegateFirstName = '';
        $this->newDelegateEmail = '';
        $this->newDelegatePassword = '';
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
            'selectedDelegateId' => ['nullable', 'integer', 'exists:users,id'],
            'form.is_active' => ['boolean'],
            'newLogo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
