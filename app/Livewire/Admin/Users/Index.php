<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin')]
#[Title('Usuarios')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    /** all | active | inactive */
    public string $statusFilter = 'all';

    /** all | admin | organizador | delegado */
    public string $roleFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'last_name' => '',
        'first_name' => '',
        'email' => '',
        'password' => '',
        'is_active' => true,
    ];

    /** @var array<int, string> */
    public array $selectedRoles = [];

    /**
     * boot() corre en CADA request (no solo en la carga inicial como
     * mount()): el middleware role:admin de la ruta no se reaplica en las
     * llamadas /livewire/update. UserAccountService lo revalida igual.
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

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function availableRoles()
    {
        return Role::orderBy('name')->pluck('name');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->editingId = $user->id;
        $this->form = [
            ...$this->splitName($user->name),
            'email' => $user->email,
            'password' => '',
            'is_active' => $user->is_active,
        ];
        $this->selectedRoles = $user->roles->pluck('name')->all();
        $this->showModal = true;
    }

    public function save(UserAccountService $service): void
    {
        $validated = $this->validate($this->rules(), [
            'selectedRoles.required' => 'Selecciona al menos un rol.',
            'selectedRoles.*.in' => 'Uno de los roles seleccionados no existe.',
        ])['form'];

        // Salvaguarda de UX: evita que un admin se quite su propio rol o
        // se desactive a si mismo por error y quede fuera del sistema
        // (la salvaguarda real de "no dejar el sistema sin admin" vive
        // en UserAccountService::delete(), esto es solo para update()).
        if (
            $this->editingId === auth()->id()
            && (! in_array('admin', $this->selectedRoles, true) || ! $validated['is_active'])
        ) {
            $this->addError('form.is_active', 'No puedes quitarte el rol admin ni desactivar tu propia cuenta.');

            return;
        }

        // users.name es una sola columna (asi lo genera Jetstream): se
        // arman apellidos+nombres aqui, igual que en el resto del
        // sistema (Player::fullName(), alta rapida de delegado), sin
        // agregar columnas nuevas a la tabla.
        $data = [
            'name' => trim("{$validated['first_name']} {$validated['last_name']}"),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_active' => $validated['is_active'],
        ];

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $service->update($user, $data, $this->selectedRoles, auth()->user());
        } else {
            if (empty($data['password'])) {
                $this->addError('form.password', 'La contraseña es obligatoria para una cuenta nueva.');

                return;
            }

            $service->register($data, $this->selectedRoles, auth()->user());
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Usuario guardado correctamente.');
    }

    public function delete(int $id, UserAccountService $service): void
    {
        $user = User::findOrFail($id);

        $result = $service->delete($user, auth()->user());

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Usuario eliminado.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = User::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn($q) => $q->where('is_active', false))
            // role() de Spatie lanza RoleDoesNotExist con un nombre invalido:
            // solo se aplica si el valor es un rol real.
            ->when(
                $this->availableRoles()->contains($this->roleFilter),
                fn($q) => $q->role($this->roleFilter)
            )
            ->with('roles:id,name')
            ->orderBy($this->sortColumn(), $this->sortOrder());

        return view('livewire.admin.users.index', [
            'users' => $query->paginate($this->perPageLimit()),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['name', 'email', 'is_active', 'created_at'];
    }

    /**
     * Separacion "mejor esfuerzo" de un name existente en first/last para
     * precargar el formulario de edicion: es lossy (p.ej. "Administrador"
     * sin apellido queda con last_name vacio), pero es la unica opcion
     * posible ya que users.name siempre fue una sola columna.
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
        ];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'last_name' => '',
            'first_name' => '',
            'email' => '',
            'password' => '',
            'is_active' => true,
        ];
        $this->selectedRoles = [];
        $this->resetErrorBag();
    }

    private function rules(): array
    {
        return [
            'form.last_name' => ['required', 'string', 'max:150'],
            'form.first_name' => ['required', 'string', 'max:150'],
            'form.email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'form.password' => ['nullable', 'string', 'min:8'],
            'form.is_active' => ['boolean'],
            'selectedRoles' => ['required', 'array'],
            'selectedRoles.*' => ['string', Rule::in($this->availableRoles()->all())],
        ];
    }
}
