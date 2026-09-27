<?php

declare(strict_types=1);

namespace App\Livewire\Venues;

use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Venue;
use App\Services\VenueService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Sedes')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithPagination;

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
        'address' => '',
        'latitude' => '',
        'longitude' => '',
        'is_active' => true,
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Venue::class);
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
        $this->authorize('create', Venue::class);

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $venue = Venue::findOrFail($id);
        $this->authorize('update', $venue);

        $this->editingId = $venue->id;
        $this->form = [
            'name' => $venue->name,
            'address' => $venue->address ?? '',
            'latitude' => $venue->latitude ?? '',
            'longitude' => $venue->longitude ?? '',
            'is_active' => $venue->is_active,
        ];
        $this->showModal = true;
    }

    public function save(VenueService $service): void
    {
        $data = $this->validate($this->rules())['form'];

        // Inputs vacios llegan como '': las columnas son NULL.
        foreach (['address', 'latitude', 'longitude'] as $field) {
            $data[$field] = in_array($data[$field], ['', null], true) ? null : $data[$field];
        }

        if ($this->editingId) {
            $venue = Venue::findOrFail($this->editingId);
            $this->authorize('update', $venue);
            $service->update($venue, $data);
        } else {
            $this->authorize('create', Venue::class);
            $service->register($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Sede guardada correctamente.');
    }

    public function delete(int $id, VenueService $service): void
    {
        $venue = Venue::findOrFail($id);
        $this->authorize('delete', $venue);

        $result = $service->delete($venue);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Sede eliminada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = Venue::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('address', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('matches')
            ->orderBy($this->sortColumn(), $this->sortOrder());

        return view('livewire.venues.index', [
            'venues' => $query->paginate($this->perPageLimit()),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['name', 'address', 'matches_count', 'is_active'];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'address' => '',
            'latitude' => '',
            'longitude' => '',
            'is_active' => true,
        ];
        $this->resetErrorBag();
    }

    /**
     * unique(name, address) en la BD no detecta duplicados cuando address
     * es NULL (MySQL permite varios NULL en un indice unico), por eso la
     * regla compara explicitamente con whereNull en ese caso.
     * Coordenadas: ambas o ninguna.
     */
    private function rules(): array
    {
        $address = trim((string) $this->form['address']);

        return [
            'form.name' => [
                'required', 'string', 'max:100',
                Rule::unique('venues', 'name')
                    ->where(fn ($q) => $address === '' ? $q->whereNull('address') : $q->where('address', $address))
                    ->ignore($this->editingId),
            ],
            'form.address' => ['nullable', 'string', 'max:255'],
            'form.latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:form.longitude'],
            'form.longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:form.latitude'],
            'form.is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => 'nombre',
            'form.address' => 'dirección',
            'form.latitude' => 'latitud',
            'form.longitude' => 'longitud',
        ];
    }
}
