<?php

declare(strict_types=1);

namespace App\Livewire\GamesEditions;

use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\GamesEdition;
use App\Models\Venue;
use App\Services\GamesEditionService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Ediciones (Olimpiadas)')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    /** all | active | inactive */
    public string $statusFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'start_date';

    public string $sortDirection = 'desc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'name' => '',
        'start_date' => '',
        'end_date' => '',
        'host_venue_id' => '',
        'is_active' => true,
    ];

    /**
     * Modulo solo-admin sin Policy: boot() corre en CADA request (el
     * middleware role:admin de la ruta no se reaplica en /livewire/update).
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
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

    /**
     * Sedes activas + la sede actual de la edicion en edicion (aunque se
     * haya desactivado despues, para no perderla del selector).
     */
    public function venues(): Collection
    {
        $currentVenueId = $this->editingId ? GamesEdition::find($this->editingId)?->host_venue_id : null;

        return Venue::where('is_active', true)
            ->when($currentVenueId, fn ($q) => $q->orWhere('id', $currentVenueId))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $edition = GamesEdition::findOrFail($id);

        $this->editingId = $edition->id;
        $this->form = [
            'name' => $edition->name,
            'start_date' => $edition->start_date?->format('Y-m-d') ?? '',
            'end_date' => $edition->end_date?->format('Y-m-d') ?? '',
            'host_venue_id' => $edition->host_venue_id ?? '',
            'is_active' => $edition->is_active,
        ];
        $this->showModal = true;
    }

    public function save(GamesEditionService $service): void
    {
        $data = $this->validate($this->rules())['form'];

        foreach (['start_date', 'end_date', 'host_venue_id'] as $field) {
            $data[$field] = in_array($data[$field], ['', null], true) ? null : $data[$field];
        }

        if ($this->editingId) {
            $service->update(GamesEdition::findOrFail($this->editingId), $data);
        } else {
            $service->register($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Edición guardada correctamente.');
    }

    public function delete(int $id, GamesEditionService $service): void
    {
        $result = $service->delete(GamesEdition::findOrFail($id));

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Edición eliminada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = GamesEdition::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('seasons')
            ->with('hostVenue:id,name')
            ->orderBy($this->sortColumn(), $this->sortOrder());

        return view('livewire.games-editions.index', [
            'editions' => $query->paginate($this->perPageLimit()),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['start_date', 'name', 'end_date', 'seasons_count', 'is_active'];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'start_date' => '',
            'end_date' => '',
            'host_venue_id' => '',
            'is_active' => true,
        ];
        $this->resetErrorBag();
    }

    private function rules(): array
    {
        $hasStart = ! in_array($this->form['start_date'], ['', null], true);

        return [
            'form.name' => [
                'required', 'string', 'max:150',
                Rule::unique('games_editions', 'name')->ignore($this->editingId),
            ],
            'form.start_date' => ['nullable', 'date'],
            'form.end_date' => array_filter(['nullable', 'date', $hasStart ? 'after_or_equal:form.start_date' : null]),
            'form.host_venue_id' => ['nullable', 'integer', Rule::exists('venues', 'id')],
            'form.is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => 'nombre',
            'form.start_date' => 'fecha de inicio',
            'form.end_date' => 'fecha de fin',
            'form.host_venue_id' => 'sede anfitriona',
        ];
    }
}
