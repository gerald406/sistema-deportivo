<?php

declare(strict_types=1);

namespace App\Livewire\Players;

use App\Livewire\Concerns\Sortable;
use App\Models\Player;
use App\Services\PlayerRegistrationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Jugadores')]
class Index extends Component
{
    use Sortable, WithFileUploads, WithPagination;

    public string $search = '';

    /** all | active | inactive */
    public string $statusFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'last_name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'dni' => '',
        'first_name' => '',
        'last_name' => '',
        'birth_date' => '',
        'is_active' => true,
    ];

    /** Foto nueva pendiente de subir (Livewire\WithFileUploads). */
    public $newPhoto = null;

    /** URL de la foto actual, para mostrarla en el modal al editar. */
    public ?string $existingPhotoUrl = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Player::class);
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
        $this->authorize('create', Player::class);

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $player = Player::findOrFail($id);
        $this->authorize('update', $player);

        $this->editingId = $player->id;
        $this->form = [
            'dni' => $player->dni,
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'birth_date' => $player->birth_date?->format('Y-m-d') ?? '',
            'is_active' => $player->is_active,
        ];
        $this->existingPhotoUrl = $player->photo_path ? Storage::disk('public')->url($player->photo_path) : null;
        $this->showModal = true;
    }

    public function save(PlayerRegistrationService $service): void
    {
        $validated = $this->validate($this->rules());
        $data = $validated['form'];

        if ($this->editingId) {
            $player = Player::findOrFail($this->editingId);
            $this->authorize('update', $player);

            if ($this->newPhoto) {
                $this->deletePhotoIfExists($player->photo_path);
                $data['photo_path'] = $this->newPhoto->store('players', 'public');
            }

            $service->update($player, $data);
        } else {
            $this->authorize('create', Player::class);

            if ($this->newPhoto) {
                $data['photo_path'] = $this->newPhoto->store('players', 'public');
            }

            $service->register($data, auth()->user());
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Jugador guardado correctamente.');
    }

    public function delete(int $id, PlayerRegistrationService $service): void
    {
        $player = Player::findOrFail($id);
        $this->authorize('delete', $player);

        if (! $service->delete($player)) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar: el jugador tiene inscripciones registradas. Desactívalo en su lugar.');

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Jugador eliminado.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = Player::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('dni', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy($this->sortField, $this->sortDirection);

        return view('livewire.players.index', [
            'players' => $query->paginate($this->perPage),
        ]);
    }

    private function deletePhotoIfExists(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'dni' => '',
            'first_name' => '',
            'last_name' => '',
            'birth_date' => '',
            'is_active' => true,
        ];
        $this->newPhoto = null;
        $this->existingPhotoUrl = null;
        $this->resetErrorBag();
    }

    private function rules(): array
    {
        return [
            'form.dni' => [
                'required',
                'digits_between:8,12',
                Rule::unique('players', 'dni')->ignore($this->editingId),
            ],
            'form.first_name' => ['required', 'string', 'max:100'],
            'form.last_name' => ['required', 'string', 'max:100'],
            'form.birth_date' => ['required', 'date', 'before:today'],
            'form.is_active' => ['boolean'],
            'newPhoto' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
