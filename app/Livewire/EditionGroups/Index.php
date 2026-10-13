<?php

declare(strict_types=1);

namespace App\Livewire\EditionGroups;

use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\SelectsEdition;
use App\Livewire\Concerns\Sortable;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Services\EditionGroupService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Series de la olimpiada elegida. Sin paginacion a proposito: una
 * olimpiada tiene un punado de series y se quieren ver todas juntas con
 * sus delegaciones.
 */
#[Layout('layouts.admin')]
#[Title('Series')]
class Index extends Component
{
    use ExportsExcel, SelectsEdition, Sortable;

    public string $sortField = 'sort_order';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'name' => '',
        'sort_order' => '',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', GamesEdition::class);
    }

    public function create(): void
    {
        $edition = $this->editionOrFail();
        $this->authorize('manage', $edition);

        $this->resetForm();
        $this->form['sort_order'] = (int) $edition->groups()->max('sort_order') + 1;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $group = $this->findGroup($id);

        $this->editingId = $group->id;
        $this->form = [
            'name' => $group->name,
            'sort_order' => $group->sort_order,
        ];
        $this->showModal = true;
    }

    public function save(EditionGroupService $service): void
    {
        $data = $this->validate($this->rules())['form'];
        $actor = auth()->user();

        if ($this->editingId) {
            $service->update($this->findGroup($this->editingId), $data, $actor);
        } else {
            $edition = $this->editionOrFail();
            $this->authorize('manage', $edition);
            $service->register($edition, $data, $actor);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Serie guardada correctamente.');
    }

    public function delete(int $id, EditionGroupService $service): void
    {
        $result = $service->delete($this->findGroup($id), auth()->user());

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Serie eliminada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $edition = $this->edition();

        return view('livewire.edition-groups.index', [
            'edition' => $edition,
            'groups' => $this->groupsOf($edition),
        ]);
    }

    /** Series de la olimpiada en el orden elegido: las comparten render() y la exportacion a Excel. */
    private function groupsOf(?GamesEdition $edition): Collection
    {
        return $edition
            ? GamesEditionGroup::where('games_edition_id', $edition->id)
                ->withCount('editionTeams')
                ->with(['editionTeams' => fn ($q) => $q->with('team:id,name')])
                ->orderBy($this->sortColumn(), $this->sortOrder())
                ->orderBy('name')
                ->get()
            : collect();
    }

    protected function authorizeExport(): void
    {
        $this->authorize('viewAny', GamesEdition::class);
    }

    protected function exportTitle(): string
    {
        return 'Series';
    }

    protected function exportRows(): iterable
    {
        return $this->groupsOf($this->edition());
    }

    protected function exportColumns(): array
    {
        return [
            'Orden' => fn (GamesEditionGroup $g) => $g->sort_order,
            'Serie' => fn (GamesEditionGroup $g) => $g->name,
            'N.º de delegaciones' => fn (GamesEditionGroup $g) => $g->edition_teams_count,
            'Delegaciones' => fn (GamesEditionGroup $g) => $g->editionTeams->pluck('team.name')->sort()->implode(', '),
        ];
    }

    protected function sortableFields(): array
    {
        return ['sort_order', 'name', 'edition_teams_count'];
    }

    /**
     * El id llega del navegador: se busca dentro de la olimpiada elegida
     * (que a su vez esta acotada a las que el usuario gestiona).
     */
    private function findGroup(int $id): GamesEditionGroup
    {
        $edition = $this->editionOrFail();
        $this->authorize('manage', $edition);

        return GamesEditionGroup::where('games_edition_id', $edition->id)->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'sort_order' => '',
        ];
        $this->resetErrorBag();
    }

    /** Que el nombre no se repita en la olimpiada lo valida EditionGroupService. */
    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:50'],
            'form.sort_order' => ['nullable', 'integer', 'between:0,999'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => 'nombre',
            'form.sort_order' => 'orden',
        ];
    }
}
