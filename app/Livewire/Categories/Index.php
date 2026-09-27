<?php

declare(strict_types=1);

namespace App\Livewire\Categories;

use App\Enums\Gender;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Categorías')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    /** all | active | inactive */
    public string $statusFilter = 'all';

    /** all | M | F | X */
    public string $genderFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'name' => '',
        'min_age' => '',
        'max_age' => '',
        'gender' => 'X',
        'is_active' => true,
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Category::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingGenderFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function genderOptions(): array
    {
        return Gender::cases();
    }

    public function create(): void
    {
        $this->authorize('create', Category::class);

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->authorize('update', $category);

        $this->editingId = $category->id;
        $this->form = [
            'name' => $category->name,
            'min_age' => $category->min_age ?? '',
            'max_age' => $category->max_age ?? '',
            'gender' => $category->gender->value,
            'is_active' => $category->is_active,
        ];
        $this->showModal = true;
    }

    public function save(CategoryService $service): void
    {
        $data = $this->validate($this->rules())['form'];

        // Los inputs numericos vacios llegan como '': la columna es INT
        // NULL, asi que se normalizan a null antes de ir al Service.
        $data['min_age'] = $data['min_age'] === '' || $data['min_age'] === null ? null : (int) $data['min_age'];
        $data['max_age'] = $data['max_age'] === '' || $data['max_age'] === null ? null : (int) $data['max_age'];

        if ($this->editingId) {
            $category = Category::findOrFail($this->editingId);
            $this->authorize('update', $category);
            $service->update($category, $data);
        } else {
            $this->authorize('create', Category::class);
            $service->register($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Categoría guardada correctamente.');
    }

    public function delete(int $id, CategoryService $service): void
    {
        $category = Category::findOrFail($id);
        $this->authorize('delete', $category);

        $result = $service->delete($category);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Categoría eliminada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = Category::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(Gender::tryFrom($this->genderFilter), fn ($q, Gender $gender) => $q->where('gender', $gender->value))
            ->withCount('seasonTeams')
            ->orderBy($this->sortColumn(), $this->sortOrder());

        return view('livewire.categories.index', [
            'categories' => $query->paginate($this->perPageLimit()),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['name', 'min_age', 'max_age', 'gender', 'season_teams_count', 'is_active'];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'min_age' => '',
            'max_age' => '',
            'gender' => 'X',
            'is_active' => true,
        ];
        $this->resetErrorBag();
    }

    /**
     * El nombre no es unico en la BD (solo indexado) porque "Sub-17"
     * puede existir en Varones y en Damas; lo que no tiene sentido es
     * repetir el par nombre + genero.
     */
    private function rules(): array
    {
        $hasMin = ! in_array($this->form['min_age'], ['', null], true);

        return [
            'form.name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'name')
                    ->where('gender', $this->form['gender'])
                    ->ignore($this->editingId),
            ],
            'form.gender' => ['required', Rule::enum(Gender::class)],
            'form.min_age' => ['nullable', 'integer', 'min:0', 'max:99'],
            'form.max_age' => array_filter(['nullable', 'integer', 'min:0', 'max:99', $hasMin ? 'gte:form.min_age' : null]),
            'form.is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => 'nombre',
            'form.min_age' => 'edad mínima',
            'form.max_age' => 'edad máxima',
        ];
    }
}
