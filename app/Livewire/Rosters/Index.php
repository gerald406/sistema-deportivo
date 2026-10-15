<?php

declare(strict_types=1);

namespace App\Livewire\Rosters;

use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\SeasonTeamPlayerStatus;
use App\Enums\StaffType;
use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\SportPosition;
use App\Models\Team;
use App\Services\PlayerRegistrationService;
use App\Services\RosterRuleService;
use App\Services\SeasonTeamPlayerService;
use App\Services\SeasonTeamService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Planteles. Para agregar un participante se elige la COMPETENCIA y la
 * DELEGACION: la inscripcion de la delegacion en la competencia se crea
 * sola con el primer participante (SeasonTeamPlayerService::registerInCompetition()).
 */
#[Layout('layouts.admin')]
#[Title('Planteles')]
class Index extends Component
{
    use ExportsExcel, LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    /** Inscripcion (season_team) cuyo plantel se muestra; '' = todas. En la URL para llegar desde Inscripcion por disciplina. */
    #[Url(as: 'inscripcion')]
    public string $seasonTeamFilter = '';

    /** all | active | injured | suspended | inactive */
    public string $rosterStatusFilter = 'all';

    public int $perPage = 25;

    public string $sortField = 'shirt_number';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    /** Buscador de participantes dentro del modal (solo los de sus delegaciones). */
    public string $playerSearch = '';

    // --- Agregar varios (D23): seleccion multiple sobre una disciplina y delegacion ---
    public bool $showBulk = false;

    /** Disciplina y delegacion del panel (precargadas si hay un plantel filtrado). */
    public string $bulkSeasonId = '';

    public string $bulkTeamId = '';

    public string $bulkSearch = '';

    public string $bulkStaffFilter = '';

    public string $bulkEmploymentFilter = '';

    /** Ocultar a quienes tienen observaciones o no pueden entrar. */
    public bool $bulkOnlyEligible = false;

    /** @var list<string> ids de players marcados (wire:model de checkboxes: strings) */
    public array $bulkSelected = [];

    /** Resumen del ultimo "Agregar seleccionados": added / warnings / rejected. */
    public ?array $bulkResult = null;

    public array $form = [
        'season_id' => '',
        'team_id' => '',
        'player_id' => '',
        'shirt_number' => '',
        'position_id' => '',
        'is_captain' => false,
        'status' => 'active',
        'enrolled_at' => '',
        'observations' => '',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', SeasonTeamPlayer::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'seasonTeamFilter', 'rosterStatusFilter', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** Al cambiar de competencia: otra lista de delegaciones y de posiciones. */
    public function updatedFormSeasonId(): void
    {
        $this->form['position_id'] = '';

        $delegations = $this->delegationOptions();
        $this->form['team_id'] = $delegations->count() === 1 ? $delegations->first()->id : '';
    }

    public function statusOptions(): array
    {
        return SeasonTeamPlayerStatus::cases();
    }

    /** Competencias abiertas en las que el usuario puede armar planteles. */
    public function competitionOptions(): Collection
    {
        return app(SeasonTeamPlayerService::class)->competitionsFor(auth()->user());
    }

    /** Delegaciones del usuario en la competencia elegida (el organizador: todas las inscritas). */
    public function delegationOptions(): Collection
    {
        $season = $this->formSeason();

        return $season
            ? app(SeasonTeamPlayerService::class)->delegationsFor(auth()->user(), $season)
            : collect();
    }

    /**
     * Participantes activos que coinciden con la busqueda, solo dentro del
     * alcance del usuario (PlayerRegistrationService::scopeVisibleTo()): los
     * de sus delegaciones, nunca los de otra.
     */
    public function playerOptions(): Collection
    {
        if (mb_strlen(trim($this->playerSearch)) < 2) {
            return collect();
        }

        $term = trim($this->playerSearch);

        return $this->reachablePlayers()
            ->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('dni', 'like', "%{$term}%"))
            ->orderBy('last_name')
            ->limit(15)
            ->get(['id', 'first_name', 'last_name', 'dni', 'birth_date', 'gender']);
    }

    public function selectedPlayer(): ?Player
    {
        return ctype_digit((string) $this->form['player_id']) ? $this->reachablePlayers(activeOnly: false)->find($this->form['player_id']) : null;
    }

    public function selectPlayer(int $id): void
    {
        $this->form['player_id'] = $this->reachablePlayers()->findOrFail($id)->id;
        $this->playerSearch = '';
    }

    /**
     * Reglas que el participante elegido incumple en la competencia y
     * delegacion elegidas, para avisar ANTES de guardar.
     *
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function selectionCheck(): array
    {
        $season = $this->formSeason();
        $player = $this->selectedPlayer();
        $team = $season ? $this->delegationOptions()->firstWhere('id', (int) $this->form['team_id']) : null;

        return $season && $team && $player && ! $this->editingId
            ? app(RosterRuleService::class)->evaluate($season, $team, $player)
            : ['errors' => [], 'warnings' => []];
    }

    /** Posiciones del deporte de la competencia elegida. */
    public function positionOptions(): Collection
    {
        $sportId = $this->editingId
            ? SeasonTeamPlayer::with('seasonTeam.season:id,sport_id')->find($this->editingId)?->seasonTeam->season->sport_id
            : $this->formSeason()?->sport_id;

        return $sportId
            ? SportPosition::where('sport_id', $sportId)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
            : collect();
    }

    public function create(): void
    {
        $this->authorize('create', SeasonTeamPlayer::class);

        $this->resetForm();

        // Con un plantel filtrado, el formulario arranca en esa competencia y delegacion.
        if (ctype_digit($this->seasonTeamFilter)) {
            $current = app(SeasonTeamService::class)->scopeVisibleTo(SeasonTeam::query(), auth()->user())->find((int) $this->seasonTeamFilter);

            if ($current && $this->competitionOptions()->contains('id', $current->season_id)) {
                $this->form['season_id'] = $current->season_id;
                $this->form['team_id'] = $this->delegationOptions()->contains('id', $current->team_id) ? $current->team_id : '';
            }
        }

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $entry = $this->findVisibleEntry($id);
        $this->authorize('update', $entry);

        $this->editingId = $entry->id;
        $this->form = [
            'season_id' => $entry->seasonTeam->season_id,
            'team_id' => $entry->seasonTeam->team_id,
            'player_id' => $entry->player_id,
            'shirt_number' => $entry->shirt_number ?? '',
            'position_id' => $entry->position_id ?? '',
            'is_captain' => $entry->is_captain,
            'status' => $entry->status->value,
            'enrolled_at' => $entry->enrolled_at->format('Y-m-d'),
            'observations' => $entry->observations ?? '',
        ];
        $this->showModal = true;
    }

    public function save(SeasonTeamPlayerService $service, RosterRuleService $rules): void
    {
        $data = $this->validate($this->rules())['form'];

        foreach (['shirt_number', 'position_id'] as $field) {
            $data[$field] = in_array($data[$field], ['', null], true) ? null : (int) $data[$field];
        }
        $data['observations'] = trim((string) $data['observations']) === '' ? null : $data['observations'];

        $warnings = [];

        if ($this->editingId) {
            $entry = $this->findVisibleEntry($this->editingId);
            $this->authorize('update', $entry);
            $service->update($entry, $data);
        } else {
            $this->authorize('create', SeasonTeamPlayer::class);

            // La competencia se busca dentro de las que el usuario puede
            // usar; la delegacion la autoriza el Service (403 si es ajena).
            $season = $this->formSeason()
                ?? throw ValidationException::withMessages(['form.season_id' => 'Elige una competencia de la lista.']);
            $team = Team::findOrFail((int) $data['team_id']);

            $entry = $service->registerInCompetition($season, $team, $data, auth()->user());
            $warnings = $rules->evaluate($season, $team, $entry->player)['warnings'];
        }

        $this->showModal = false;
        $this->resetForm();

        if ($warnings !== []) {
            $this->dispatch('toast', type: 'warning', message: 'Participante agregado con observaciones: '.implode(' ', $warnings));

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Plantel actualizado correctamente.');
    }

    /** Disciplinas en las que el usuario puede armar planteles (mismas que el alta individual). */
    public function bulkSeasonOptions(): Collection
    {
        return $this->competitionOptions();
    }

    /** Delegaciones del usuario en la disciplina elegida del panel (el organizador: todas las inscritas). */
    public function bulkTeamOptions(): Collection
    {
        $season = $this->bulkSeason();

        return $season ? app(SeasonTeamPlayerService::class)->delegationsFor(auth()->user(), $season) : collect();
    }

    /** Disciplina del panel, buscada dentro de las que el usuario puede usar (el id llega del navegador). */
    public function bulkSeason(): ?Season
    {
        return ctype_digit($this->bulkSeasonId) ? $this->bulkSeasonOptions()->firstWhere('id', (int) $this->bulkSeasonId) : null;
    }

    /** Delegacion del panel, buscada dentro de las del usuario en esa disciplina. */
    public function bulkTeam(): ?Team
    {
        return ctype_digit($this->bulkTeamId) ? $this->bulkTeamOptions()->firstWhere('id', (int) $this->bulkTeamId) : null;
    }

    /** Inscripcion de la delegacion en la disciplina, si ya existe (para el conteo y excluir a su plantel). */
    public function bulkSeasonTeam(): ?SeasonTeam
    {
        $season = $this->bulkSeason();
        $team = $this->bulkTeam();

        return $season && $team ? SeasonTeam::where('season_id', $season->id)->where('team_id', $team->id)->first() : null;
    }

    /**
     * Abre "Agregar varios". Con un plantel filtrado llega precargado; si
     * no, se elige disciplina y delegacion en el mismo panel (con una sola
     * delegacion posible, se elige sola).
     */
    public function openBulk(): void
    {
        $this->authorize('create', SeasonTeamPlayer::class);

        $this->bulkSeasonId = $this->bulkTeamId = '';

        if (ctype_digit($this->seasonTeamFilter)) {
            $current = app(SeasonTeamService::class)->scopeVisibleTo(SeasonTeam::query(), auth()->user())->find((int) $this->seasonTeamFilter);

            if ($current && $this->bulkSeasonOptions()->contains('id', $current->season_id)) {
                $this->bulkSeasonId = (string) $current->season_id;
                $this->bulkTeamId = $this->bulkTeamOptions()->contains('id', $current->team_id) ? (string) $current->team_id : '';
            }
        }

        $this->resetBulkSelection();
        $this->bulkSearch = $this->bulkStaffFilter = $this->bulkEmploymentFilter = '';
        $this->bulkOnlyEligible = false;
        $this->showBulk = true;
    }

    public function updatedBulkSeasonId(): void
    {
        $teams = $this->bulkTeamOptions();
        $this->bulkTeamId = $teams->count() === 1 ? (string) $teams->first()->id : '';
        $this->resetBulkSelection();
    }

    public function updatedBulkTeamId(): void
    {
        $this->resetBulkSelection();
    }

    public function closeBulk(): void
    {
        $this->showBulk = false;
        $this->resetBulkSelection();
    }

    public function staffTypeOptions(): array
    {
        return StaffType::cases();
    }

    public function employmentTypeOptions(): array
    {
        return EmploymentType::cases();
    }

    /**
     * Candidatos para la disciplina y delegacion del panel: participantes
     * activos de las delegaciones del usuario (mismo alcance que el
     * buscador), del genero de la disciplina y que aun no estan en ese
     * plantel. Cada uno con su aptitud segun RosterRuleService: ok, warn
     * (entra con observaciones) o blocked (no puede entrar: ya esta en
     * otra delegacion en esta disciplina, o la fila del cuadro es
     * bloqueante).
     *
     * @return Collection<int, object{player: Player, state: string, reasons: list<string>}>
     */
    public function bulkCandidates(): Collection
    {
        $season = $this->bulkSeason();
        $team = $this->bulkTeam();

        if (! $season || ! $team) {
            return collect();
        }

        $rules = app(RosterRuleService::class);
        $term = trim($this->bulkSearch);

        return $this->reachablePlayers()
            ->whereDoesntHave('seasonTeamPlayers', fn ($q) => $q->whereHas('seasonTeam', fn ($s) => $s->where('season_id', $season->id)->where('team_id', $team->id)))
            ->when(in_array($season->gender, [Gender::Male, Gender::Female], true), fn ($q) => $q->where('gender', $season->gender->value))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('dni', 'like', "%{$term}%")))
            ->when(StaffType::tryFrom($this->bulkStaffFilter), fn ($q, StaffType $t) => $q->where('staff_type', $t->value))
            ->when(EmploymentType::tryFrom($this->bulkEmploymentFilter), fn ($q, EmploymentType $t) => $q->where('employment_type', $t->value))
            ->with(['seasonTeamPlayers' => fn ($q) => $q->whereHas('seasonTeam', fn ($s) => $s->where('season_id', $season->id))->with('seasonTeam.team:id,name')])
            ->orderBy('last_name')->orderBy('first_name')
            ->limit(SeasonTeamPlayerService::BULK_MAX)
            ->get()
            ->map(function (Player $player) use ($season, $team, $rules) {
                $clash = $player->seasonTeamPlayers->first();

                if ($clash) {
                    return (object) ['player' => $player, 'state' => 'blocked', 'reasons' => ["Ya está con {$clash->seasonTeam->team->name} en esta disciplina."]];
                }

                $reasons = $rules->violations($season, $team, $player);
                $state = $reasons === [] ? 'ok' : ($rules->isBlocking($season) ? 'blocked' : 'warn');

                return (object) ['player' => $player, 'state' => $state, 'reasons' => $reasons];
            })
            ->when($this->bulkOnlyEligible, fn ($c) => $c->where('state', 'ok'))
            ->values();
    }

    /** Marca todos los aptos (sin observaciones) de la lista visible. */
    public function selectAllEligible(): void
    {
        $this->bulkSelected = $this->bulkCandidates()->where('state', 'ok')
            ->map(fn ($c) => (string) $c->player->id)->values()->all();
    }

    /**
     * Agrega a los seleccionados. Si la delegacion aun no estaba inscrita en
     * la disciplina, la inscribe el primero (registerInCompetition(), con el
     * cierre de inscripciones). Despues, la tabla muestra ese plantel.
     */
    public function addSelected(SeasonTeamPlayerService $service): void
    {
        $this->authorize('create', SeasonTeamPlayer::class);
        $season = $this->bulkSeason() ?? throw ValidationException::withMessages(['bulkSeasonId' => 'Elige la disciplina.']);
        $team = $this->bulkTeam() ?? throw ValidationException::withMessages(['bulkTeamId' => 'Elige la delegación.']);

        try {
            $result = $service->registerMany($season, $team, $this->bulkSelected, auth()->user());
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first());

            return;
        }

        $this->bulkResult = $result;
        $this->bulkSelected = [];

        if ($seasonTeam = $this->bulkSeasonTeam()) {
            $this->seasonTeamFilter = (string) $seasonTeam->id;
        }
        $this->resetPage();

        $added = count($result['added']);
        $rejected = count($result['rejected']);

        $this->dispatch('toast', type: $rejected > 0 ? 'warning' : 'success', message: $rejected > 0
            ? "Se agregaron {$added}; {$rejected} no se pudieron agregar (ver el resumen)."
            : "Se agregaron {$added} al plantel.");
    }

    private function resetBulkSelection(): void
    {
        $this->bulkSelected = [];
        $this->bulkResult = null;
        $this->resetErrorBag(['bulkSeasonId', 'bulkTeamId']);
    }

    /**
     * Edicion en linea de camiseta, posicion o capitania (D23). Las reglas
     * las aplica SeasonTeamPlayerService::updateInline(); un error vuelve
     * como aviso y la fila se redibuja con el dato guardado.
     */
    public function updateInline(int $id, string $field, mixed $value, SeasonTeamPlayerService $service): void
    {
        $entry = $this->findVisibleEntry($id);
        $this->authorize('update', $entry);

        try {
            $service->updateInline($entry, $field, $value, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'Guardado.');
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first());
        }
    }

    public function delete(int $id, SeasonTeamPlayerService $service): void
    {
        $entry = $this->findVisibleEntry($id);
        $this->authorize('delete', $entry);

        $result = $service->delete($entry);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Participante quitado del plantel.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render(SeasonTeamService $seasonTeams, RosterRuleService $rules)
    {
        $user = auth()->user();

        $query = $this->listingQuery();

        $entries = $query->paginate($this->perPageLimit());

        // Observaciones de reglamento por participante y aviso de minimo
        // por plantel, solo de las filas de esta pagina.
        $notices = [];
        $missing = [];

        foreach ($entries as $entry) {
            $seasonTeam = $entry->seasonTeam;

            if ($entry->status !== SeasonTeamPlayerStatus::Inactive) {
                $notices[$entry->id] = $rules->violations($seasonTeam->season, $seasonTeam->team, $entry->player);
            }

            $missing[$seasonTeam->id] ??= $rules->missingForMinimum(
                $seasonTeam->season,
                SeasonTeamPlayer::where('season_team_id', $seasonTeam->id)->where('status', '!=', SeasonTeamPlayerStatus::Inactive->value)->count(),
            );
        }

        // Posiciones por deporte, para el selector en linea de esta pagina.
        $positions = SportPosition::whereIn('sport_id', $entries->getCollection()->pluck('seasonTeam.season.sport_id')->filter()->unique())
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'sport_id', 'name'])->groupBy('sport_id');

        return view('livewire.rosters.index', [
            'entries' => $entries,
            'positionsBySport' => $positions,
            'notices' => $notices,
            'missing' => $missing,
            // Mismo alcance que /inscripciones: el filtro no lista delegaciones ajenas.
            'filterSeasonTeams' => $seasonTeams->scopeVisibleTo(SeasonTeam::query(), $user)
                ->with(['team:id,name', 'season:id,name'])->get()->sortBy(fn ($st) => $st->team->name),
        ]);
    }

    /** Listado filtrado y ordenado: lo comparten render() y la exportacion a Excel. */
    private function listingQuery(): Builder
    {
        $user = auth()->user();
        $service = app(SeasonTeamPlayerService::class);

        return $service->scopeVisibleTo(SeasonTeamPlayer::query(), $user)
            ->addSelect([
                'season_team_player.*',
                // Alias para ordenar por apellido sin JOIN.
                'player_last_name' => Player::select('last_name')->whereColumn('players.id', 'season_team_player.player_id'),
            ])
            ->when($this->search !== '', fn ($q) => $q->whereHas('player', fn ($p) => $p
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
                ->orWhere('dni', 'like', "%{$this->search}%")))
            ->when(ctype_digit($this->seasonTeamFilter), fn ($q) => $q->where('season_team_id', (int) $this->seasonTeamFilter))
            ->when(SeasonTeamPlayerStatus::tryFrom($this->rosterStatusFilter), fn ($q, $s) => $q->where('status', $s->value))
            ->with([
                'player:id,first_name,last_name,dni,birth_date,gender,photo_path,documents_verified_at', 'position:id,name',
                'seasonTeam:id,season_id,team_id,category_id', 'seasonTeam.team:id,name',
                'seasonTeam.season:id,name,status,games_edition_id,games_edition_event_id,category_id,gender,sport_id',
                'seasonTeam.season.gamesEdition:id,name,organizer_id,start_date',
                'seasonTeam.season.event:id,min_players,max_players,rules_blocking',
                'seasonTeam.season.category:id,name,min_age',
            ])
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('player_last_name');
    }

    protected function authorizeExport(): void
    {
        $this->authorize('viewAny', SeasonTeamPlayer::class);
    }

    protected function exportTitle(): string
    {
        return 'Planteles';
    }

    protected function exportRows(): iterable
    {
        return $this->listingQuery()->get();
    }

    protected function exportColumns(): array
    {
        return [
            'N°' => fn (SeasonTeamPlayer $e) => $e->shirt_number,
            'Apellidos' => fn (SeasonTeamPlayer $e) => $e->player->last_name,
            'Nombres' => fn (SeasonTeamPlayer $e) => $e->player->first_name,
            'DNI' => fn (SeasonTeamPlayer $e) => $e->player->dni,
            'Nacimiento' => fn (SeasonTeamPlayer $e) => $e->player->birth_date,
            'Capitán' => fn (SeasonTeamPlayer $e) => (bool) $e->is_captain,
            'Documentos' => fn (SeasonTeamPlayer $e) => $e->player->documents_verified_at ? 'Verificados' : 'Por verificar',
            'Delegación' => fn (SeasonTeamPlayer $e) => $e->seasonTeam->team->name,
            'Competencia' => fn (SeasonTeamPlayer $e) => $e->seasonTeam->season->name,
            'Posición' => fn (SeasonTeamPlayer $e) => $e->position?->name,
            'Estado' => fn (SeasonTeamPlayer $e) => $e->status->label(),
            'Alta' => fn (SeasonTeamPlayer $e) => $e->enrolled_at,
        ];
    }

    protected function sortableFields(): array
    {
        return ['shirt_number', 'player_last_name', 'status', 'enrolled_at'];
    }

    private function reachablePlayers(bool $activeOnly = true): Builder
    {
        return app(PlayerRegistrationService::class)
            ->scopeVisibleTo(Player::query(), auth()->user())
            ->when($activeOnly, fn ($q) => $q->where('is_active', true));
    }

    /**
     * La competencia del formulario, buscada dentro de las que el usuario
     * puede usar (el id llega del navegador).
     */
    private function formSeason(): ?Season
    {
        return ctype_digit((string) $this->form['season_id'])
            ? $this->competitionOptions()->firstWhere('id', (int) $this->form['season_id'])
            : null;
    }

    /**
     * Ids que llegan del navegador se buscan acotados al alcance del
     * usuario (scopeVisibleTo): un convocado de otra olimpiada/equipo da 404
     * antes de llegar a la Policy.
     */
    private function findVisibleEntry(int $id): SeasonTeamPlayer
    {
        return app(SeasonTeamPlayerService::class)
            ->scopeVisibleTo(SeasonTeamPlayer::query(), auth()->user())
            ->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->playerSearch = '';
        $this->form = [
            'season_id' => '',
            'team_id' => '',
            'player_id' => '',
            'shirt_number' => '',
            'position_id' => '',
            'is_captain' => false,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
            'observations' => '',
        ];
        $this->resetErrorBag();
    }

    /** Reglas de negocio del plantel: SeasonTeamPlayerService y RosterRuleService. */
    private function rules(): array
    {
        return [
            'form.season_id' => ['required', 'integer'],
            'form.team_id' => ['required', 'integer', Rule::exists('teams', 'id')],
            'form.player_id' => ['required', 'integer', Rule::exists('players', 'id')],
            'form.shirt_number' => ['nullable', 'integer', 'between:0,999'],
            'form.position_id' => ['nullable', 'integer'],
            'form.is_captain' => ['boolean'],
            'form.status' => ['required', Rule::enum(SeasonTeamPlayerStatus::class)],
            'form.enrolled_at' => ['required', 'date'],
            'form.observations' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.season_id' => 'competencia',
            'form.team_id' => 'delegación',
            'form.player_id' => 'participante',
            'form.shirt_number' => 'número de camiseta',
            'form.enrolled_at' => 'fecha de alta',
        ];
    }
}
