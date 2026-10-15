<?php

declare(strict_types=1);

namespace App\Livewire\Players;

use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\StaffType;
use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Player;
use App\Policies\PlayerPolicy;
use App\Services\PlayerRegistrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Participantes')]
class Index extends Component
{
    use ExportsExcel, LimitsPerPage, Sortable, WithFileUploads, WithPagination;

    public string $search = '';

    /** all | active | inactive */
    public string $statusFilter = 'all';

    /** '' | with | without (resolucion en PDF) */
    public string $resolutionFilter = '';

    public int $perPage = 15;

    /** PDF de hasta 5 MB, revisado por contenido (lo revalida PlayerRegistrationService). */
    private const RESOLUTION_RULES = ['file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.PlayerRegistrationService::RESOLUTION_MAX_KB];

    public string $sortField = 'last_name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'dni' => '',
        'first_name' => '',
        'last_name' => '',
        'birth_date' => '',
        'gender' => '',
        'staff_type' => '',
        'employment_type' => '',
        'is_active' => true,
    ];

    /** Foto nueva pendiente de subir (Livewire\WithFileUploads). */
    public $newPhoto = null;

    /** URL de la foto actual, para mostrarla en el modal al editar. */
    public ?string $existingPhotoUrl = null;

    /** Resolucion (PDF) adjunta en el formulario de alta/edicion. */
    public $newResolution = null;

    // --- Modal "Resolucion" (delegado u organizador, desde cada fila) ---
    public bool $showResolution = false;

    public ?int $resolutionPlayerId = null;

    /** PDF elegido en el modal "Resolucion". */
    public $resolutionFile = null;

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

    public function updatingResolutionFilter(): void
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
        $player = $this->findVisiblePlayer($id);
        $this->authorize('update', $player);

        $this->editingId = $player->id;
        $this->form = [
            'dni' => $player->dni,
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'birth_date' => $player->birth_date?->format('Y-m-d') ?? '',
            'gender' => $player->gender?->value ?? '',
            'staff_type' => $player->staff_type?->value ?? '',
            'employment_type' => $player->employment_type?->value ?? '',
            'is_active' => $player->is_active,
        ];
        $this->existingPhotoUrl = $player->photo_path ? Storage::disk('public')->url($player->photo_path) : null;
        $this->showModal = true;
    }

    public function save(PlayerRegistrationService $service): void
    {
        $validated = $this->validate($this->rules());
        $data = $validated['form'];
        $actor = auth()->user();

        if ($this->editingId) {
            $player = $this->findVisiblePlayer($this->editingId);
            $this->authorize('update', $player);

            // Verificado = el PDF queda como evidencia: se avisa antes de guardar nada.
            if ($this->newResolution && ! $actor->can('manageResolution', $player)) {
                $this->addError('newResolution', 'Los documentos ya están verificados: solo el organizador puede reemplazar la resolución.');

                return;
            }

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

            $player = $service->register($data, $actor);
        }

        if ($this->newResolution) {
            $service->attachResolution($player, $this->newResolution, $actor);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Participante guardado correctamente.');
    }

    public function delete(int $id, PlayerRegistrationService $service): void
    {
        $player = $this->findVisiblePlayer($id);
        $this->authorize('delete', $player);

        $result = $service->delete($player);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Participante eliminado.');
    }

    /** Documentos revisados: solo admin u organizador (PlayerPolicy::verifyDocuments). */
    public function toggleDocuments(int $id, PlayerRegistrationService $service): void
    {
        $player = $this->findVisiblePlayer($id);
        $this->authorize('verifyDocuments', $player);

        $service->setDocumentsVerified($player, $player->documents_verified_at === null, auth()->user());

        $this->dispatch('toast', type: 'success', message: $player->documents_verified_at
            ? 'Documentos marcados como verificados.'
            : 'Se quitó la verificación de documentos.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    /** Participante del modal "Resolucion": visible para el usuario (si no, 404). */
    public function resolutionPlayer(): ?Player
    {
        return $this->resolutionPlayerId ? $this->findVisiblePlayer($this->resolutionPlayerId)->load('resolutionUploader:id,name') : null;
    }

    public function openResolution(int $id): void
    {
        $player = $this->findVisiblePlayer($id);
        $this->authorize('view', $player);

        $this->resolutionPlayerId = $player->id;
        $this->resolutionFile = null;
        $this->resetErrorBag();
        $this->showResolution = true;
    }

    public function saveResolution(PlayerRegistrationService $service): void
    {
        $player = $this->findVisiblePlayer((int) $this->resolutionPlayerId);
        $this->authorize('manageResolution', $player);

        $this->validate(['resolutionFile' => self::RESOLUTION_RULES], [], ['resolutionFile' => 'resolución']);

        $service->attachResolution($player, $this->resolutionFile, auth()->user());

        $this->closeResolution();
        $this->dispatch('toast', type: 'success', message: 'Resolución guardada.');
    }

    public function removeResolution(PlayerRegistrationService $service): void
    {
        $player = $this->findVisiblePlayer((int) $this->resolutionPlayerId);
        $this->authorize('manageResolution', $player);

        $service->removeResolution($player, auth()->user());

        $this->closeResolution();
        $this->dispatch('toast', type: 'success', message: 'Se quitó la resolución.');
    }

    public function closeResolution(): void
    {
        $this->showResolution = false;
        $this->resolutionPlayerId = null;
        $this->resolutionFile = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = $this->listingQuery();

        return view('livewire.players.index', [
            'players' => $query->paginate($this->perPageLimit()),
        ]);
    }

    /** Listado filtrado y ordenado: lo comparten render() y la exportacion a Excel. */
    private function listingQuery(): Builder
    {
        $user = auth()->user();
        $service = app(PlayerRegistrationService::class);

        return $service->scopeVisibleTo(Player::query(), $user)
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('dni', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->resolutionFilter === 'with', fn ($q) => $q->whereNotNull('resolution_path'))
            ->when($this->resolutionFilter === 'without', fn ($q) => $q->whereNull('resolution_path'))
            // Precarga la pertenencia para los @can('update'/'delete') de
            // cada fila (PlayerPolicy la lee de este atributo): 0 consultas
            // extra por fila en vez de 2. Admin no lo necesita (Gate::before).
            ->when(! $user->hasRole('admin') && $user->hasRole('delegado'), fn ($q) => $q->withExists([
                'seasonTeamPlayers as '.PlayerPolicy::managedAttribute($user) => PlayerPolicy::managedRosterConstraint($user),
            ]))
            ->orderBy($this->sortColumn(), $this->sortOrder());
    }

    protected function authorizeExport(): void
    {
        $this->authorize('viewAny', Player::class);
    }

    protected function exportTitle(): string
    {
        return 'Participantes';
    }

    protected function exportRows(): iterable
    {
        return $this->listingQuery()->get();
    }

    protected function exportColumns(): array
    {
        return [
            'Apellidos' => fn (Player $p) => $p->last_name,
            'Nombres' => fn (Player $p) => $p->first_name,
            'DNI' => fn (Player $p) => $p->dni,
            'Nacimiento' => fn (Player $p) => $p->birth_date,
            'Género' => fn (Player $p) => $p->gender?->label(),
            'Tipo' => fn (Player $p) => $p->staff_type?->label(),
            'Condición' => fn (Player $p) => $p->employment_type?->label(),
            'Documentos' => fn (Player $p) => $p->documents_verified_at ? 'Verificados' : 'Por verificar',
            'Resolución' => fn (Player $p) => $p->resolution_path ? 'Sí ('.$p->resolution_uploaded_at?->format('d/m/Y').')' : 'No',
            'Estado' => fn (Player $p) => $p->is_active ? 'Activo' : 'Inactivo',
        ];
    }

    protected function sortableFields(): array
    {
        return ['last_name', 'first_name', 'dni', 'birth_date', 'is_active'];
    }

    /**
     * Ids que llegan del navegador se buscan acotados al alcance del
     * usuario (scopeVisibleTo): quien mande el id de un participante fuera de
     * su olimpiada/equipo recibe 404 antes de llegar a la Policy.
     */
    private function findVisiblePlayer(int $id): Player
    {
        return app(PlayerRegistrationService::class)
            ->scopeVisibleTo(Player::query(), auth()->user())
            ->findOrFail($id);
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
            'gender' => '',
            'staff_type' => '',
            'employment_type' => '',
            'is_active' => true,
        ];
        $this->newPhoto = null;
        $this->existingPhotoUrl = null;
        $this->newResolution = null;
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
            // Damas o varones: lo exigen las competencias por genero.
            'form.gender' => ['required', Rule::in([Gender::Male->value, Gender::Female->value])],
            'form.staff_type' => ['nullable', Rule::enum(StaffType::class)],
            'form.employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'form.is_active' => ['boolean'],
            'newPhoto' => ['nullable', 'image', 'max:2048'],
            'newResolution' => ['nullable', ...self::RESOLUTION_RULES],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.dni' => 'DNI',
            'form.first_name' => 'nombres',
            'form.last_name' => 'apellidos',
            'form.birth_date' => 'fecha de nacimiento',
            'form.gender' => 'género',
            'form.staff_type' => 'tipo de personal',
            'form.employment_type' => 'condición',
            'newResolution' => 'resolución',
        ];
    }
}
