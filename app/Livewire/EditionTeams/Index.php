<?php

declare(strict_types=1);

namespace App\Livewire\EditionTeams;

use App\Enums\EditionTeamStatus;
use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\SelectsEdition;
use App\Livewire\Concerns\Sortable;
use App\Models\GamesEdition;
use App\Models\GamesEditionTeam;
use App\Services\DelegateAccountService;
use App\Services\EditionTeamService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Delegaciones inscritas en la olimpiada elegida: serie, delegado titular
 * y suplente, estado y pago. Desde aqui el organizador (o el admin) crea
 * tambien la cuenta de un delegado nuevo (DelegateAccountService).
 */
#[Layout('layouts.admin')]
#[Title('Delegaciones inscritas')]
class Index extends Component
{
    use ExportsExcel, LimitsPerPage, SelectsEdition, Sortable, WithPagination;

    public string $search = '';

    /** En la URL para llegar desde Series ya filtrado. */
    #[Url(as: 'serie')]
    public string $groupFilter = '';

    /** all | registered | withdrawn | disqualified */
    public string $statusFilter = 'all';

    public int $perPage = 25;

    public string $sortField = 'team_name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'team_id' => '',
        'group_id' => '',
        'delegate_id' => '',
        'substitute_delegate_id' => '',
        'status' => 'registered',
        'fee_paid' => false,
        'observations' => '',
    ];

    /** Alta rapida de una cuenta de delegado dentro del modal. */
    public bool $creatingDelegate = false;

    public array $newDelegate = [
        'name' => '',
        'email' => '',
        'password' => '',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', GamesEdition::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingGroupFilter(): void
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

    public function statusOptions(): array
    {
        return EditionTeamStatus::cases();
    }

    public function groupOptions(): Collection
    {
        return $this->edition()?->groups()->get(['id', 'name']) ?? collect();
    }

    /** Delegaciones a cargo del usuario que aun no estan inscritas en la olimpiada elegida. */
    public function teamOptions(): Collection
    {
        $edition = $this->edition();

        if (! $edition) {
            return collect();
        }

        return app(EditionTeamService::class)->assignableTeams(auth()->user())
            ->whereNotIn('teams.id', $edition->editionTeams()->select('team_id'))
            ->orderBy('teams.name')
            ->get(['teams.id', 'teams.name']);
    }

    public function delegateOptions(): Collection
    {
        return app(EditionTeamService::class)->delegateOptions();
    }

    public function create(): void
    {
        $this->authorize('manage', $this->editionOrFail());

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $entry = $this->findEntry($id);

        $this->resetForm();
        $this->editingId = $entry->id;
        $this->form = [
            'team_id' => $entry->team_id,
            'group_id' => $entry->group_id ?? '',
            'delegate_id' => $entry->delegate_id ?? '',
            'substitute_delegate_id' => $entry->substitute_delegate_id ?? '',
            'status' => $entry->status->value,
            'fee_paid' => $entry->fee_paid,
            'observations' => $entry->observations ?? '',
        ];
        $this->showModal = true;
    }

    /**
     * Fases de la serie actual donde la delegacion en edicion ya esta en la
     * llave o el podio, si el formulario la cambia de serie (D28): avisa,
     * no bloquea.
     *
     * @return list<string>
     */
    public function groupChangeWarning(): array
    {
        if (! $this->editingId) {
            return [];
        }

        $entry = $this->findEntry($this->editingId);

        return (string) ($entry->group_id ?? '') === (string) $this->form['group_id']
            ? []
            : app(EditionTeamService::class)->stagesUsingGroup($entry)->all();
    }

    public function save(EditionTeamService $service): void
    {
        $data = $this->validate($this->rules())['form'];
        $actor = auth()->user();

        if ($this->editingId) {
            $warning = $this->groupChangeWarning();
            $service->update($this->findEntry($this->editingId), $data, $actor);

            if ($warning !== []) {
                $this->showModal = false;
                $this->resetForm();
                $this->dispatch('toast', type: 'warning', message: 'Delegación movida de serie. Revisa en Fases la llave o el podio de: '.implode(', ', $warning).'.');

                return;
            }
        } else {
            $edition = $this->editionOrFail();
            $this->authorize('manage', $edition);
            $service->register($edition, $data, $actor);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Delegación guardada correctamente.');
    }

    public function delete(int $id, EditionTeamService $service): void
    {
        $result = $service->delete($this->findEntry($id), auth()->user());

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Inscripción de la delegación eliminada.');
    }

    public function toggleCreatingDelegate(): void
    {
        $this->creatingDelegate = ! $this->creatingDelegate;
        $this->newDelegate = ['name' => '', 'email' => '', 'password' => ''];
        $this->resetErrorBag(['newDelegate.name', 'newDelegate.email', 'newDelegate.password']);
    }

    /**
     * Crea la cuenta y la deja elegida como titular (o como suplente si ya
     * hay titular). Quien puede crearla lo revalida DelegateAccountService:
     * admin, u organizador con una olimpiada a cargo; el rol siempre es
     * "delegado".
     */
    public function saveNewDelegateAccount(DelegateAccountService $service): void
    {
        $this->authorize('manage', $this->editionOrFail());

        $data = $this->validate([
            'newDelegate.name' => ['required', 'string', 'max:255'],
            'newDelegate.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'newDelegate.password' => ['required', 'string', 'min:8'],
        ], attributes: [
            'newDelegate.name' => 'nombre',
            'newDelegate.email' => 'correo',
            'newDelegate.password' => 'contraseña',
        ])['newDelegate'];

        $delegate = $service->createQuickAccount($data, auth()->user());

        $field = $this->form['delegate_id'] === '' ? 'delegate_id' : 'substitute_delegate_id';
        $this->form[$field] = $delegate->id;

        $this->toggleCreatingDelegate();
        $this->dispatch('toast', type: 'success', message: "Cuenta de delegado creada: {$delegate->name}.");
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $edition = $this->edition();

        return view('livewire.edition-teams.index', [
            'edition' => $edition,
            'entries' => $edition ? $this->listingQuery($edition)->paginate($this->perPageLimit()) : null,
        ]);
    }

    /** Listado filtrado y ordenado: lo comparten render() y la exportacion a Excel. */
    private function listingQuery(GamesEdition $edition): Builder
    {
        return GamesEditionTeam::query()
            ->where('games_edition_teams.games_edition_id', $edition->id)
            ->join('teams', 'teams.id', '=', 'games_edition_teams.team_id')
            ->leftJoin('games_edition_groups', 'games_edition_groups.id', '=', 'games_edition_teams.group_id')
            ->select('games_edition_teams.*', 'teams.name as team_name', 'games_edition_groups.sort_order as group_order')
            ->when($this->search !== '', fn ($q) => $q->where('teams.name', 'like', "%{$this->search}%"))
            ->when(ctype_digit($this->groupFilter), fn ($q) => $q->where('games_edition_teams.group_id', (int) $this->groupFilter))
            ->when(EditionTeamStatus::tryFrom($this->statusFilter), fn ($q, EditionTeamStatus $s) => $q->where('games_edition_teams.status', $s->value))
            ->with(['team:id,name,logo_path', 'group:id,name', 'delegate:id,name,email', 'substituteDelegate:id,name,email'])
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('teams.name');
    }

    protected function authorizeExport(): void
    {
        $this->authorize('viewAny', GamesEdition::class);
    }

    protected function exportTitle(): string
    {
        return 'Delegaciones inscritas';
    }

    protected function exportRows(): iterable
    {
        $edition = $this->edition();

        return $edition ? $this->listingQuery($edition)->get() : [];
    }

    protected function exportColumns(): array
    {
        return [
            'Delegación' => fn (GamesEditionTeam $e) => $e->team->name,
            'Serie' => fn (GamesEditionTeam $e) => $e->group?->name,
            'Delegado titular' => fn (GamesEditionTeam $e) => $e->delegate?->name,
            'Correo del titular' => fn (GamesEditionTeam $e) => $e->delegate?->email,
            'Delegado suplente' => fn (GamesEditionTeam $e) => $e->substituteDelegate?->name,
            'Correo del suplente' => fn (GamesEditionTeam $e) => $e->substituteDelegate?->email,
            'Pago' => fn (GamesEditionTeam $e) => $e->fee_paid ? 'Registrado' : 'Pendiente',
            'Estado' => fn (GamesEditionTeam $e) => $e->status->label(),
        ];
    }

    protected function sortableFields(): array
    {
        return ['team_name', 'group_order', 'status', 'fee_paid'];
    }

    /**
     * El id llega del navegador: se busca dentro de la olimpiada elegida
     * (acotada a las que el usuario gestiona).
     */
    private function findEntry(int $id): GamesEditionTeam
    {
        $edition = $this->editionOrFail();
        $this->authorize('manage', $edition);

        return GamesEditionTeam::where('games_edition_id', $edition->id)->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'team_id' => '',
            'group_id' => '',
            'delegate_id' => '',
            'substitute_delegate_id' => '',
            'status' => 'registered',
            'fee_paid' => false,
            'observations' => '',
        ];
        $this->creatingDelegate = false;
        $this->newDelegate = ['name' => '', 'email' => '', 'password' => ''];
        $this->resetErrorBag();
    }

    /**
     * Aqui solo forma y tipos. Que la serie sea de esta olimpiada, que los
     * delegados tengan el rol y sean distintos, y que la delegacion no este
     * ya inscrita lo valida EditionTeamService.
     */
    private function rules(): array
    {
        return [
            'form.team_id' => ['required', 'integer'],
            'form.group_id' => ['nullable', 'integer'],
            'form.delegate_id' => ['nullable', 'integer'],
            'form.substitute_delegate_id' => ['nullable', 'integer'],
            'form.status' => ['required', 'string'],
            'form.fee_paid' => ['boolean'],
            'form.observations' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.team_id' => 'delegación',
            'form.group_id' => 'serie',
            'form.delegate_id' => 'delegado titular',
            'form.substitute_delegate_id' => 'delegado suplente',
            'form.status' => 'estado',
            'form.observations' => 'observaciones',
        ];
    }
}
