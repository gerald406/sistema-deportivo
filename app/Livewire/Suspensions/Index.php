<?php

declare(strict_types=1);

namespace App\Livewire\Suspensions;

use App\Enums\MatchStatus;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\SeasonTeamPlayer;
use App\Models\Suspension;
use App\Services\SuspensionService;
use App\Support\ParticipantLabel;
use App\Support\SeasonAccess;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Sanciones')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    public string $seasonFilter = '';

    /** all | pending | served */
    public string $servedFilter = 'pending';

    public int $perPage = 25;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'season_id' => '',
        'match_id' => '',
        'season_team_player_id' => '',
        'reason' => '',
        'matches_suspended' => 1,
        'is_served' => false,
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Suspension::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSeasonFilter(): void
    {
        $this->resetPage();
    }

    public function updatingServedFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedFormSeasonId(): void
    {
        $this->form['match_id'] = '';
        $this->form['season_team_player_id'] = '';
    }

    public function seasonOptions(): Collection
    {
        return SeasonAccess::manageableSeasons(auth()->user(), includeClosed: true);
    }

    /** Partidos no cancelados de la temporada elegida (origen de la sancion). */
    public function matchOptions(): Collection
    {
        return ctype_digit((string) $this->form['season_id'])
            ? GameMatch::with(['matchday:id,name', 'participants' => ParticipantLabel::eagerLoad()])
                ->where('season_id', $this->form['season_id'])
                ->where('status', '!=', MatchStatus::Canceled->value)
                ->orderBy('scheduled_at')->get()
                ->map(fn ($m) => ['id' => $m->id, 'label' => $m->matchday->name . ' — ' . $m->participants->map(fn ($p) => ParticipantLabel::for($p))->join(' vs ')])
            : collect();
    }

    /** Planteles de la temporada elegida. */
    public function playerOptions(): Collection
    {
        return ctype_digit((string) $this->form['season_id'])
            ? SeasonTeamPlayer::with(['player:id,first_name,last_name', 'seasonTeam.team:id,name'])
                ->whereHas('seasonTeam', fn ($q) => $q->where('season_id', $this->form['season_id']))
                ->get()->sortBy(fn ($e) => $e->seasonTeam->team->name . $e->player->last_name)->values()
            : collect();
    }

    public function create(): void
    {
        $this->authorize('create', Suspension::class);
        $this->resetForm();

        if (ctype_digit($this->seasonFilter)) {
            $this->form['season_id'] = (int) $this->seasonFilter;
        }

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $suspension = Suspension::findOrFail($id);
        $this->authorize('update', $suspension);

        $this->editingId = $suspension->id;
        $this->form = [
            'season_id' => $suspension->season_id,
            'match_id' => $suspension->match_id,
            'season_team_player_id' => $suspension->season_team_player_id,
            'reason' => $suspension->reason,
            'matches_suspended' => $suspension->matches_suspended,
            'is_served' => $suspension->is_served,
        ];
        $this->showModal = true;
    }

    public function save(SuspensionService $service): void
    {
        $data = $this->validate($this->rules())['form'];

        if ($this->editingId) {
            $suspension = Suspension::findOrFail($this->editingId);
            $this->authorize('update', $suspension);
            $service->update($suspension, $data, auth()->user());
        } else {
            $this->authorize('create', Suspension::class);
            $service->register($data, auth()->user());
        }

        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: 'Sanción guardada.');
    }

    public function toggleServed(int $id, SuspensionService $service): void
    {
        $suspension = Suspension::findOrFail($id);
        $this->authorize('update', $suspension);
        $service->toggleServed($suspension, auth()->user());

        $this->dispatch('toast', type: 'success', message: $suspension->is_served ? 'Sanción marcada como cumplida.' : 'Sanción marcada como pendiente.');
    }

    public function delete(int $id, SuspensionService $service): void
    {
        $suspension = Suspension::findOrFail($id);
        $this->authorize('delete', $suspension);

        $result = $service->delete($suspension, auth()->user());

        $this->dispatch('toast', type: $result === true ? 'success' : 'error',
            message: $result === true ? 'Sanción eliminada.' : $result);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $user = auth()->user();
        $term = trim($this->search);

        $query = Suspension::query()
            ->select('suspensions.*')
            ->addSelect(['player_last_name' => Player::select('players.last_name')
                ->join('season_team_player', 'season_team_player.player_id', '=', 'players.id')
                ->whereColumn('season_team_player.id', 'suspensions.season_team_player_id')])
            ->unless($user->hasRole('admin'), fn ($q) => $q->whereHas('season.tournament', fn ($t) => $t->where('organizer_id', $user->id)))
            ->when($term !== '', fn ($q) => $q->whereHas('seasonTeamPlayer.player', fn ($p) => $p->where('last_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%")))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->where('season_id', (int) $this->seasonFilter))
            ->when($this->servedFilter === 'pending', fn ($q) => $q->where('is_served', false))
            ->when($this->servedFilter === 'served', fn ($q) => $q->where('is_served', true))
            ->with([
                'season:id,name,tournament_id', 'season.tournament:id,name,organizer_id',
                'seasonTeamPlayer:id,season_team_id,player_id,status', 'seasonTeamPlayer.player:id,first_name,last_name', 'seasonTeamPlayer.seasonTeam.team:id,name',
                'match:id,matchday_id,scheduled_at', 'match.matchday:id,name',
            ])
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('suspensions.id');

        return view('livewire.suspensions.index', [
            'suspensions' => $query->paginate($this->perPageLimit()),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['created_at', 'player_last_name', 'matches_suspended', 'is_served'];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = ['season_id' => '', 'match_id' => '', 'season_team_player_id' => '', 'reason' => '', 'matches_suspended' => 1, 'is_served' => false];
        $this->resetErrorBag();
    }

    /** Reglas de negocio (jugador de la temporada, partido no cancelado): SuspensionService. */
    private function rules(): array
    {
        return [
            'form.season_id' => ['required', 'integer', Rule::exists('seasons', 'id')],
            'form.match_id' => ['required', 'integer', Rule::exists('matches', 'id')->where('season_id', (int) $this->form['season_id'])],
            'form.season_team_player_id' => ['required', 'integer', Rule::exists('season_team_player', 'id')],
            'form.reason' => ['required', 'string', 'min:3', 'max:255'],
            'form.matches_suspended' => ['required', 'integer', 'between:1,50'],
            'form.is_served' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.season_id' => 'temporada',
            'form.match_id' => 'partido de origen',
            'form.season_team_player_id' => 'jugador',
            'form.reason' => 'motivo',
            'form.matches_suspended' => 'partidos de sanción',
        ];
    }
}
