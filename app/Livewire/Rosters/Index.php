<?php

declare(strict_types=1);

namespace App\Livewire\Rosters;

use App\Enums\SeasonTeamPlayerStatus;
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
    use LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    /** Inscripcion (season_team) cuyo plantel se muestra; '' = todas. */
    public string $seasonTeamFilter = '';

    /** all | active | injured | suspended | inactive */
    public string $rosterStatusFilter = 'all';

    public int $perPage = 25;

    public string $sortField = 'shirt_number';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    /**
     * Modo carnets (?carnets=1): checkboxes de seleccion multiple y boton
     * "Generar seleccionados". Solo cambia la vista; cada carnet lo
     * autoriza PlayerCardController con SeasonTeamPlayerPolicy::printCard.
     */
    #[Url(as: 'carnets')]
    public bool $cardMode = false;

    /** Buscador de participantes dentro del modal (solo los de sus delegaciones). */
    public string $playerSearch = '';

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

    public function render(SeasonTeamPlayerService $service, SeasonTeamService $seasonTeams, RosterRuleService $rules)
    {
        $user = auth()->user();

        $query = $service->scopeVisibleTo(SeasonTeamPlayer::query(), $user)
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
                'seasonTeam.season:id,name,status,games_edition_id,games_edition_event_id,category_id,gender',
                'seasonTeam.season.gamesEdition:id,name,organizer_id,start_date',
                'seasonTeam.season.event:id,min_players,max_players,rules_blocking',
                'seasonTeam.season.category:id,name,min_age',
            ])
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('player_last_name');

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

        return view('livewire.rosters.index', [
            'entries' => $entries,
            'notices' => $notices,
            'missing' => $missing,
            // Mismo alcance que /inscripciones: el filtro no lista delegaciones ajenas.
            'filterSeasonTeams' => $seasonTeams->scopeVisibleTo(SeasonTeam::query(), $user)
                ->with(['team:id,name', 'season:id,name'])->get()->sortBy(fn ($st) => $st->team->name),
        ]);
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
