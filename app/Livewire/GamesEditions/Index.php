<?php

declare(strict_types=1);

namespace App\Livewire\GamesEditions;

use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\GamesEdition;
use App\Models\Venue;
use App\Services\GamesEditionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Olimpiadas')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithFileUploads, WithPagination;

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
        'organizer_id' => '',
        'start_date' => '',
        'end_date' => '',
        'host_venue_id' => '',
        'is_active' => true,
        'yellow_cards_limit' => '',
        'captain_red_matches' => '',
    ];

    /** Logo nuevo pendiente de subir (Livewire\WithFileUploads). */
    public $newLogo = null;

    /** URL del logo actual, para mostrarlo en el modal al editar. */
    public ?string $existingLogoUrl = null;

    public function mount(): void
    {
        $this->authorize('viewAny', GamesEdition::class);
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
        return app(GamesEditionService::class)->organizerCandidates();
    }

    public function isAdmin(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * Sedes activas + la sede actual de la olimpiada en edicion (aunque se
     * haya desactivado despues, para no perderla del selector).
     */
    public function venues(): Collection
    {
        $currentVenueId = $this->editingId ? $this->findManaged($this->editingId)->host_venue_id : null;

        return Venue::where('is_active', true)
            ->when($currentVenueId, fn ($q) => $q->orWhere('id', $currentVenueId))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(): void
    {
        $this->authorize('create', GamesEdition::class);

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $edition = $this->findManaged($id);
        $this->authorize('update', $edition);

        $this->editingId = $edition->id;
        $this->form = [
            'name' => $edition->name,
            'organizer_id' => $edition->organizer_id,
            'start_date' => $edition->start_date?->format('Y-m-d') ?? '',
            'end_date' => $edition->end_date?->format('Y-m-d') ?? '',
            'host_venue_id' => $edition->host_venue_id ?? '',
            'is_active' => $edition->is_active,
            'yellow_cards_limit' => $edition->yellow_cards_limit ?? '',
            'captain_red_matches' => $edition->captain_red_matches ?? '',
        ];
        $this->existingLogoUrl = $edition->logo ? Storage::disk('public')->url($edition->logo) : null;
        $this->showModal = true;
    }

    public function save(GamesEditionService $service): void
    {
        $data = $this->validate($this->rules())['form'];

        // Vacio = la regla no se aplica.
        foreach (['yellow_cards_limit', 'captain_red_matches'] as $rule) {
            $data[$rule] = in_array($data[$rule] ?? '', ['', null], true) ? null : (int) $data[$rule];
        }
        $actor = auth()->user();

        foreach (['start_date', 'end_date', 'host_venue_id'] as $field) {
            $data[$field] = in_array($data[$field], ['', null], true) ? null : $data[$field];
        }

        if ($this->editingId) {
            $edition = $this->findManaged($this->editingId);
            $this->authorize('update', $edition);

            if ($this->newLogo) {
                $previousLogo = $edition->logo;
                $data['logo'] = $this->newLogo->store('editions', 'public');
            }

            $service->update($edition, $data, $actor);

            // Se borra el logo anterior solo despues de guardar: si el
            // Service rechaza el cambio, la olimpiada conserva su logo.
            if (isset($previousLogo)) {
                $this->deleteLogoIfExists($previousLogo);
            }
        } else {
            $this->authorize('create', GamesEdition::class);

            if ($this->newLogo) {
                $data['logo'] = $this->newLogo->store('editions', 'public');
            }

            $service->register($data, $actor);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Olimpiada guardada correctamente.');
    }

    public function delete(int $id, GamesEditionService $service): void
    {
        $edition = $this->findManaged($id);
        $this->authorize('delete', $edition);

        $result = $service->delete($edition);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Olimpiada eliminada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = $this->managedQuery()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('seasons')
            ->with(['hostVenue:id,name', 'organizer:id,name'])
            ->orderBy($this->sortColumn(), $this->sortOrder());

        return view('livewire.games-editions.index', [
            'editions' => $query->paginate($this->perPageLimit()),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['start_date', 'name', 'end_date', 'seasons_count', 'is_active'];
    }

    /** Olimpiadas que el usuario gestiona: el organizador solo ve las suyas. */
    private function managedQuery()
    {
        return app(GamesEditionService::class)->scopeManagedBy(GamesEdition::query(), auth()->user());
    }

    /**
     * El id llega del navegador: se busca dentro del alcance del usuario,
     * asi una olimpiada ajena da 404 antes de llegar a la Policy.
     */
    private function findManaged(int $id): GamesEdition
    {
        return $this->managedQuery()->findOrFail($id);
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
            'start_date' => '',
            'end_date' => '',
            'host_venue_id' => '',
            'is_active' => true,
            'yellow_cards_limit' => '',
            'captain_red_matches' => '',
        ];
        $this->newLogo = null;
        $this->existingLogoUrl = null;
        $this->resetErrorBag();
    }

    /**
     * organizer_id solo se valida aqui como entero; que sea un
     * organizador/admin activo (y que un organizador no pueda cambiarlo)
     * lo decide GamesEditionService::resolveOrganizerId().
     */
    private function rules(): array
    {
        $hasStart = ! in_array($this->form['start_date'], ['', null], true);

        return [
            'form.name' => [
                'required', 'string', 'max:150',
                Rule::unique('games_editions', 'name')->ignore($this->editingId),
            ],
            'form.organizer_id' => ['nullable', 'integer'],
            'form.start_date' => ['nullable', 'date'],
            'form.end_date' => array_filter(['nullable', 'date', $hasStart ? 'after_or_equal:form.start_date' : null]),
            'form.host_venue_id' => ['nullable', 'integer', Rule::exists('venues', 'id')],
            'form.is_active' => ['boolean'],
            'form.yellow_cards_limit' => ['nullable', 'integer', 'between:1,20'],
            'form.captain_red_matches' => ['nullable', 'integer', 'between:1,20'],
            'newLogo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => 'nombre',
            'form.organizer_id' => 'organizador',
            'form.start_date' => 'fecha de inicio',
            'form.end_date' => 'fecha de fin',
            'form.host_venue_id' => 'sede principal',
            'form.yellow_cards_limit' => 'amarillas para suspensión',
            'form.captain_red_matches' => 'partidos de sanción al capitán',
            'newLogo' => 'logo',
        ];
    }
}
