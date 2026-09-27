<?php

declare(strict_types=1);

namespace App\Livewire\Rosters;

use App\Enums\SeasonTeamPlayerStatus;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Player;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\SportPosition;
use App\Services\SeasonTeamPlayerService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

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

    /** Buscador de jugadores dentro del modal (catalogo global). */
    public string $playerSearch = '';

    public array $form = [
        'season_team_id' => '',
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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSeasonTeamFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRosterStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedFormSeasonTeamId(): void
    {
        $this->form['position_id'] = '';
    }

    public function statusOptions(): array
    {
        return SeasonTeamPlayerStatus::cases();
    }

    /** Inscripciones cuyo plantel puede editar el usuario. */
    public function manageableSeasonTeams(): Collection
    {
        return app(SeasonTeamPlayerService::class)->seasonTeamsFor(auth()->user());
    }

    /** Jugadores activos del catalogo global que coinciden con la busqueda. */
    public function playerOptions(): Collection
    {
        if (mb_strlen(trim($this->playerSearch)) < 2) {
            return collect();
        }

        $term = trim($this->playerSearch);

        return Player::where('is_active', true)
            ->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('dni', 'like', "%{$term}%"))
            ->orderBy('last_name')
            ->limit(15)
            ->get(['id', 'first_name', 'last_name', 'dni', 'birth_date']);
    }

    public function selectedPlayer(): ?Player
    {
        return ctype_digit((string) $this->form['player_id']) ? Player::find($this->form['player_id']) : null;
    }

    public function selectPlayer(int $id): void
    {
        $this->form['player_id'] = Player::where('is_active', true)->findOrFail($id)->id;
        $this->playerSearch = '';
    }

    /** Posiciones del deporte de la temporada de la inscripcion elegida. */
    public function positionOptions(): Collection
    {
        $seasonTeam = $this->formSeasonTeam();

        return $seasonTeam
            ? SportPosition::where('sport_id', $seasonTeam->season->sport_id)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
            : collect();
    }

    public function create(): void
    {
        $this->authorize('create', SeasonTeamPlayer::class);

        $this->resetForm();

        if (ctype_digit($this->seasonTeamFilter)
            && $this->manageableSeasonTeams()->contains('id', (int) $this->seasonTeamFilter)) {
            $this->form['season_team_id'] = (int) $this->seasonTeamFilter;
        }

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $entry = SeasonTeamPlayer::findOrFail($id);
        $this->authorize('update', $entry);

        $this->editingId = $entry->id;
        $this->form = [
            'season_team_id' => $entry->season_team_id,
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

    public function save(SeasonTeamPlayerService $service): void
    {
        $data = $this->validate($this->rules())['form'];

        foreach (['shirt_number', 'position_id'] as $field) {
            $data[$field] = in_array($data[$field], ['', null], true) ? null : (int) $data[$field];
        }
        $data['observations'] = trim((string) $data['observations']) === '' ? null : $data['observations'];

        if ($this->editingId) {
            $entry = SeasonTeamPlayer::findOrFail($this->editingId);
            $this->authorize('update', $entry);
            $service->update($entry, $data);
        } else {
            $this->authorize('create', SeasonTeamPlayer::class);
            $service->register($data, auth()->user());
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Plantel actualizado correctamente.');
    }

    public function delete(int $id, SeasonTeamPlayerService $service): void
    {
        $entry = SeasonTeamPlayer::findOrFail($id);
        $this->authorize('delete', $entry);

        $result = $service->delete($entry);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Jugador quitado del plantel.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = SeasonTeamPlayer::query()
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
                'player:id,first_name,last_name,dni,birth_date,photo_path', 'position:id,name',
                'seasonTeam:id,season_id,team_id,delegate_id,category_id', 'seasonTeam.team:id,name,delegate_id',
                'seasonTeam.season:id,name,status,tournament_id', 'seasonTeam.season.tournament:id,name,organizer_id',
                'seasonTeam.delegate:id', 'seasonTeam.team.delegate:id',
            ])
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('player_last_name');

        return view('livewire.rosters.index', [
            'entries' => $query->paginate($this->perPageLimit()),
            'filterSeasonTeams' => SeasonTeam::with(['team:id,name', 'season:id,name'])->get()->sortBy(fn ($st) => $st->team->name),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['shirt_number', 'player_last_name', 'status', 'enrolled_at'];
    }

    private function formSeasonTeam(): ?SeasonTeam
    {
        return ctype_digit((string) $this->form['season_team_id'])
            ? SeasonTeam::with('season')->find($this->form['season_team_id'])
            : null;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->playerSearch = '';
        $this->form = [
            'season_team_id' => '',
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

    /** Reglas de negocio del plantel: SeasonTeamPlayerService. */
    private function rules(): array
    {
        return [
            'form.season_team_id' => ['required', 'integer', Rule::exists('season_team', 'id')],
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
            'form.season_team_id' => 'equipo inscrito',
            'form.player_id' => 'jugador',
            'form.shirt_number' => 'número de camiseta',
            'form.enrolled_at' => 'fecha de alta',
        ];
    }
}
