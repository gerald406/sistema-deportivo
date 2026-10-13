<?php

declare(strict_types=1);

namespace App\Livewire\Seasons;

use App\Enums\Gender;
use App\Enums\SeasonStatus;
use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\SelectsEdition;
use App\Livewire\Concerns\Sortable;
use App\Models\Category;
use App\Models\Season;
use App\Models\Sport;
use App\Services\CompetitionGeneratorService;
use App\Services\SeasonService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Competencias de la olimpiada elegida (deporte x nivel x genero). Lo
 * normal es generarlas desde el cuadro de puntaje; el formulario manual
 * queda para ajustarlas o agregar alguna fuera del cuadro.
 */
#[Layout('layouts.admin')]
#[Title('Competencias')]
class Index extends Component
{
    use ExportsExcel, LimitsPerPage, SelectsEdition, Sortable, WithPagination;

    public string $search = '';

    public string $sportFilter = '';

    public string $categoryFilter = '';

    /** '' | M | F | X */
    public string $genderFilter = '';

    /** all | draft | active | closed */
    public string $seasonStatusFilter = 'all';

    public int $perPage = 25;

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'sport_id' => '',
        'category_id' => '',
        'gender' => '',
        'games_edition_event_id' => '',
        'name' => '',
        'start_date' => '',
        'end_date' => '',
        'status' => 'draft',
        'is_active' => true,
    ];

    /**
     * Sub-formulario de ScoringConfig (varias fases, minimo una). Cada
     * item: id (null si es nueva), phase_name, pts_win, pts_draw,
     * pts_loss, pts_walkover.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $phases = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Season::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'sportFilter', 'categoryFilter', 'genderFilter', 'seasonStatusFilter', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function statusOptions(): array
    {
        return SeasonStatus::cases();
    }

    public function genderOptions(): array
    {
        return Gender::cases();
    }

    public function sportOptions(): Collection
    {
        return Sport::where('is_active', true)
            ->when($this->form['sport_id'] !== '', fn ($q) => $q->orWhere('id', $this->form['sport_id']))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function categoryOptions(): Collection
    {
        return Category::where('is_active', true)
            ->when($this->form['category_id'] !== '', fn ($q) => $q->orWhere('id', $this->form['category_id']))
            ->orderBy('min_age')->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Filas del cuadro de puntaje de la olimpiada elegida. */
    public function eventOptions(): Collection
    {
        return $this->edition()?->events()->get(['id', 'label']) ?? collect();
    }

    /** Crea las competencias que falten segun el cuadro de puntaje. */
    public function generate(CompetitionGeneratorService $generator): void
    {
        $edition = $this->editionOrFail();
        $this->authorize('manage', $edition);

        $result = $generator->generate($edition, auth()->user());

        $message = "Creadas: {$result['created']}. Ya existían: {$result['existing']}.";
        if ($result['skipped'] > 0) {
            $message .= " Filas del cuadro sin deporte (no generan competencia): {$result['skipped']}.";
        }

        $this->resetPage();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function create(): void
    {
        $edition = $this->editionOrFail();
        $this->authorize('manage', $edition);

        $this->resetForm();
        $this->form['start_date'] = $edition->start_date?->format('Y-m-d') ?? '';
        $this->form['end_date'] = $edition->end_date?->format('Y-m-d') ?? '';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $season = $this->findSeason($id)->load('scoringConfigs');
        $this->authorize('update', $season);

        $this->editingId = $season->id;
        $this->form = [
            'sport_id' => $season->sport_id,
            'category_id' => $season->category_id ?? '',
            'gender' => $season->gender?->value ?? '',
            'games_edition_event_id' => $season->games_edition_event_id ?? '',
            'name' => $season->name,
            'start_date' => $season->start_date?->format('Y-m-d') ?? '',
            'end_date' => $season->end_date?->format('Y-m-d') ?? '',
            'status' => $season->status->value,
            'is_active' => $season->is_active,
        ];
        $this->phases = $season->scoringConfigs
            ->sortBy('id')
            ->map(fn ($c) => [
                'id' => $c->id,
                'phase_name' => $c->phase_name,
                'pts_win' => $c->pts_win,
                'pts_draw' => $c->pts_draw,
                'pts_loss' => $c->pts_loss,
                'pts_walkover' => $c->pts_walkover,
            ])->values()->all();

        if ($this->phases === []) {
            $this->addPhase();
        }

        $this->showModal = true;
    }

    public function addPhase(): void
    {
        $this->phases[] = [
            'id' => null,
            'phase_name' => $this->phases === [] ? CompetitionGeneratorService::DEFAULT_PHASE : '',
            'pts_win' => 3,
            'pts_draw' => 1,
            'pts_loss' => 0,
            'pts_walkover' => -1,
        ];
    }

    public function removePhase(int $index): void
    {
        if (count($this->phases) <= 1) {
            return;
        }

        unset($this->phases[$index]);
        $this->phases = array_values($this->phases);
    }

    public function save(SeasonService $service): void
    {
        $edition = $this->editionOrFail();
        $validated = $this->validate($this->rules($edition->id));
        $data = $validated['form'];
        $actor = auth()->user();

        foreach (['start_date', 'end_date', 'category_id', 'gender', 'games_edition_event_id'] as $field) {
            $data[$field] = in_array($data[$field], ['', null], true) ? null : $data[$field];
        }

        // La olimpiada no viene del formulario: es la elegida en pantalla,
        // que SelectsEdition ya acoto a las que el usuario gestiona.
        $data['games_edition_id'] = $edition->id;

        if ($this->editingId) {
            $season = $this->findSeason($this->editingId);
            $this->authorize('update', $season);
            $service->update($season, $data, $validated['phases'], $actor);
        } else {
            $this->authorize('manage', $edition);
            $service->register($data, $validated['phases'], $actor);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Competencia guardada correctamente.');
    }

    public function delete(int $id, SeasonService $service): void
    {
        $season = $this->findSeason($id);
        $this->authorize('delete', $season);

        $result = $service->delete($season);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Competencia eliminada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $edition = $this->edition();

        $query = $this->listingQuery();

        return view('livewire.seasons.index', [
            'edition' => $edition,
            'seasons' => $query->paginate($this->perPageLimit()),
            'filterSports' => Sport::orderBy('name')->get(['id', 'name']),
            'filterCategories' => Category::where('is_active', true)->orderBy('min_age')->get(['id', 'name']),
        ]);
    }

    /** Listado filtrado y ordenado: lo comparten render() y la exportacion a Excel. */
    private function listingQuery(): Builder
    {
        $edition = $this->edition();

        return Season::query()
            // Sin olimpiada elegida (o ajena) no se lista nada.
            ->where('games_edition_id', $edition?->id ?? 0)
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when(ctype_digit($this->sportFilter), fn ($q) => $q->where('sport_id', (int) $this->sportFilter))
            ->when(ctype_digit($this->categoryFilter), fn ($q) => $q->where('category_id', (int) $this->categoryFilter))
            ->when(Gender::tryFrom($this->genderFilter), fn ($q, Gender $g) => $q->where('gender', $g->value))
            ->when(SeasonStatus::tryFrom($this->seasonStatusFilter), fn ($q, SeasonStatus $s) => $q->where('status', $s->value))
            ->withCount(['seasonTeams', 'scoringConfigs'])
            ->with(['gamesEdition:id,name,organizer_id', 'sport:id,name', 'category:id,name', 'discipline:id,name'])
            ->orderBy($this->sortColumn(), $this->sortOrder());
    }

    protected function authorizeExport(): void
    {
        $this->authorize('viewAny', Season::class);
    }

    protected function exportTitle(): string
    {
        return 'Competencias';
    }

    protected function exportRows(): iterable
    {
        return $this->listingQuery()->get();
    }

    protected function exportColumns(): array
    {
        return [
            'Competencia' => fn (Season $s) => $s->name,
            'Deporte' => fn (Season $s) => $s->sport->name,
            'Prueba' => fn (Season $s) => $s->discipline?->name,
            'Nivel' => fn (Season $s) => $s->category?->name,
            'Género' => fn (Season $s) => $s->gender?->label(),
            'Inicio' => fn (Season $s) => $s->start_date,
            'Fin' => fn (Season $s) => $s->end_date,
            'Delegaciones' => fn (Season $s) => $s->season_teams_count,
            'Estado' => fn (Season $s) => $s->status->label(),
            'Activa' => fn (Season $s) => (bool) $s->is_active,
        ];
    }

    protected function sortableFields(): array
    {
        return ['start_date', 'name', 'end_date', 'status', 'season_teams_count', 'is_active'];
    }

    /** El id llega del navegador: se busca dentro de la olimpiada elegida. */
    private function findSeason(int $id): Season
    {
        return Season::where('games_edition_id', $this->editionOrFail()->id)->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'sport_id' => '',
            'category_id' => '',
            'gender' => '',
            'games_edition_event_id' => '',
            'name' => '',
            'start_date' => '',
            'end_date' => '',
            'status' => 'draft',
            'is_active' => true,
        ];
        $this->phases = [];
        $this->addPhase();
        $this->resetErrorBag();
    }

    private function rules(int $editionId): array
    {
        $hasStart = ! in_array($this->form['start_date'], ['', null], true);
        $points = ['required', 'integer', 'between:-99,99'];

        return [
            'form.sport_id' => ['required', 'integer', Rule::exists('sports', 'id')],
            'form.category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'form.gender' => ['nullable', Rule::enum(Gender::class)],
            // La fila del cuadro debe ser de ESTA olimpiada.
            'form.games_edition_event_id' => ['nullable', 'integer', Rule::exists('games_edition_events', 'id')->where('games_edition_id', $editionId)],
            'form.name' => [
                'required', 'string', 'max:100',
                Rule::unique('seasons', 'name')->where('games_edition_id', $editionId)->ignore($this->editingId),
            ],
            'form.start_date' => ['nullable', 'date'],
            'form.end_date' => array_filter(['nullable', 'date', $hasStart ? 'after_or_equal:form.start_date' : null]),
            'form.status' => ['required', Rule::enum(SeasonStatus::class)],
            'form.is_active' => ['boolean'],
            'phases' => ['required', 'array', 'min:1'],
            'phases.*.id' => ['nullable', 'integer'],
            'phases.*.phase_name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'phases.*.pts_win' => $points,
            'phases.*.pts_draw' => $points,
            'phases.*.pts_loss' => $points,
            'phases.*.pts_walkover' => $points,
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.sport_id' => 'deporte',
            'form.category_id' => 'nivel',
            'form.gender' => 'género',
            'form.games_edition_event_id' => 'fila del cuadro',
            'form.name' => 'nombre',
            'form.start_date' => 'fecha de inicio',
            'form.end_date' => 'fecha de fin',
            'phases.*.phase_name' => 'nombre de fase',
            'phases.*.pts_win' => 'puntos por victoria',
            'phases.*.pts_draw' => 'puntos por empate',
            'phases.*.pts_loss' => 'puntos por derrota',
            'phases.*.pts_walkover' => 'puntos por walkover',
        ];
    }
}
