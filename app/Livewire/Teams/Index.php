<?php

declare(strict_types=1);

namespace App\Livewire\Teams;

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
    use Sortable, WithFileUploads, WithPagination;

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
        'delegate_id' => '',
        'is_active' => true,
    ];

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

    public function delegates(): Collection
    {
        return User::role('delegado')->orderBy('name')->get(['id', 'name']);
    }

    public function isAdmin(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public function create(): void
    {
        $this->authorize('create', Team::class);

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $team = Team::findOrFail($id);
        $this->authorize('update', $team);

        $this->editingId = $team->id;
        $this->form = [
            'name' => $team->name,
            'delegate_id' => $team->delegate_id ?? '',
            'is_active' => $team->is_active,
        ];
        $this->existingLogoUrl = $team->logo_path ? Storage::disk('public')->url($team->logo_path) : null;
        $this->showModal = true;
    }

    public function save(TeamRegistrationService $service): void
    {
        $validated = $this->validate($this->rules());
        $data = $validated['form'];
        $actor = auth()->user();

        if ($this->editingId) {
            $team = Team::findOrFail($this->editingId);
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
        $team = Team::findOrFail($id);
        $this->authorize('delete', $team);

        if (! $service->delete($team)) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar: el equipo tiene inscripciones registradas. Desactívalo en su lugar.');

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

        $this->form['delegate_id'] = $delegate->id;
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
        $query = Team::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('seasonTeams')
            ->with('delegate:id,name')
            ->orderBy($this->sortField, $this->sortDirection);

        return view('livewire.teams.index', [
            'teams' => $query->paginate($this->perPage),
        ]);
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
            'delegate_id' => '',
            'is_active' => true,
        ];
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
            'form.delegate_id' => ['nullable', 'exists:users,id'],
            'form.is_active' => ['boolean'],
            'newLogo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
