<?php

declare(strict_types=1);

namespace App\Livewire\EditionEvents;

use App\Enums\GenderScope;
use App\Livewire\Concerns\SelectsEdition;
use App\Models\Category;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\Sport;
use App\Services\EditionEventService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Cuadro de puntaje de la olimpiada elegida. Lo ven el admin y el
 * organizador de la olimpiada; lo edita SOLO el admin (D7). Sin paginacion
 * ni orden por columna a proposito: es un cuadro corto que se lee en el
 * orden de las bases y se suma al pie.
 */
#[Layout('layouts.admin')]
#[Title('Cuadro de puntaje')]
class Index extends Component
{
    use SelectsEdition;

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [];

    public function mount(): void
    {
        $this->authorize('viewAny', GamesEdition::class);
        $this->resetForm();
    }

    public function create(): void
    {
        $this->ensureAdmin();
        $edition = $this->editionOrFail();

        $this->resetForm();
        $this->form['sort_order'] = (int) $edition->events()->max('sort_order') + 1;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->ensureAdmin();
        $event = $this->findEvent($id);

        $this->editingId = $event->id;
        $this->form = [
            'label' => $event->label,
            'sort_order' => $event->sort_order,
            'sport_id' => $event->sport_id ?? '',
            'category_id' => $event->category_id ?? '',
            'gender_scope' => $event->gender_scope->value,
            'separate_podiums' => $event->separate_podiums,
            'per_discipline' => $event->per_discipline,
            'pts_first' => $event->pts_first ?? '',
            'pts_second' => $event->pts_second ?? '',
            'pts_third' => $event->pts_third ?? '',
            'min_players' => $event->min_players ?? '',
            'max_players' => $event->max_players ?? '',
            'rules_blocking' => $event->rules_blocking,
            'is_active' => $event->is_active,
        ];
        $this->showModal = true;
    }

    public function save(EditionEventService $service): void
    {
        $this->ensureAdmin();
        $data = $this->validate($this->rules())['form'];
        $actor = auth()->user();

        if ($this->editingId) {
            $service->update($this->findEvent($this->editingId), $data, $actor);
        } else {
            $service->register($this->editionOrFail(), $data, $actor);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Fila del cuadro guardada correctamente.');
    }

    public function delete(int $id, EditionEventService $service): void
    {
        $this->ensureAdmin();
        $result = $service->delete($this->findEvent($id), auth()->user());

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Fila eliminada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $edition = $this->edition();

        $events = $edition
            ? $edition->events()->with(['sport:id,name', 'category:id,name'])->get()
            : collect();

        return view('livewire.edition-events.index', [
            'edition' => $edition,
            'events' => $events,
            'canEdit' => auth()->user()->hasRole('admin'),
            'sports' => Sport::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => Category::where('is_active', true)->orderBy('min_age')->orderBy('name')->get(['id', 'name']),
            'scopes' => GenderScope::cases(),
        ]);
    }

    /** El middleware de la ruta no se reaplica en /livewire/update: se revalida en cada accion. */
    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
    }

    /** El id llega del navegador: se busca dentro de la olimpiada elegida. */
    private function findEvent(int $id): GamesEditionEvent
    {
        return GamesEditionEvent::where('games_edition_id', $this->editionOrFail()->id)->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'label' => '',
            'sort_order' => '',
            'sport_id' => '',
            'category_id' => '',
            'gender_scope' => GenderScope::None->value,
            'separate_podiums' => true,
            'per_discipline' => false,
            'pts_first' => '',
            'pts_second' => '',
            'pts_third' => '',
            'min_players' => '',
            'max_players' => '',
            'rules_blocking' => false,
            'is_active' => true,
        ];
        $this->resetErrorBag();
    }

    /**
     * Etiqueta unica, orden de los puntos y minimo <= maximo los valida
     * EditionEventService.
     */
    private function rules(): array
    {
        return [
            'form.label' => ['required', 'string', 'max:150'],
            'form.sort_order' => ['nullable', 'integer', 'between:0,999'],
            'form.sport_id' => ['nullable', 'integer', Rule::exists('sports', 'id')],
            'form.category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'form.gender_scope' => ['required', Rule::enum(GenderScope::class)],
            'form.separate_podiums' => ['boolean'],
            'form.per_discipline' => ['boolean'],
            'form.pts_first' => ['nullable', 'integer', 'between:0,999'],
            'form.pts_second' => ['nullable', 'integer', 'between:0,999'],
            'form.pts_third' => ['nullable', 'integer', 'between:0,999'],
            'form.min_players' => ['nullable', 'integer', 'between:0,999'],
            'form.max_players' => ['nullable', 'integer', 'between:1,999'],
            'form.rules_blocking' => ['boolean'],
            'form.is_active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.label' => 'etiqueta',
            'form.sort_order' => 'orden',
            'form.sport_id' => 'deporte',
            'form.category_id' => 'nivel',
            'form.gender_scope' => 'alcance',
            'form.pts_first' => 'puntos del 1.º',
            'form.pts_second' => 'puntos del 2.º',
            'form.pts_third' => 'puntos del 3.º',
            'form.min_players' => 'mínimo de participantes',
            'form.max_players' => 'máximo de participantes',
        ];
    }
}
