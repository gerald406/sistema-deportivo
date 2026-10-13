<?php

declare(strict_types=1);

namespace App\Livewire\MatchEvents;

use App\Enums\MatchStatus;
use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\EventType;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Services\MatchEventService;
use App\Support\ParticipantLabel;
use App\Support\SeasonAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Incidencias')]
class Index extends Component
{
    use ExportsExcel, LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    public string $seasonFilter = '';

    public string $eventTypeFilter = '';

    public int $perPage = 25;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'match_id' => '',
        'season_team_player_id' => '',
        'event_type_id' => '',
        'minute' => '',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', MatchEvent::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSeasonFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEventTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedFormMatchId(): void
    {
        $this->form['season_team_player_id'] = '';
        $this->form['event_type_id'] = '';
    }

    /** Partidos Programados, de competencias que el usuario gestiona y cuyo deporte tiene tipos de incidencia. */
    public function matchOptions(): Collection
    {
        $seasonIds = SeasonAccess::manageableSeasons(auth()->user())->pluck('id');

        return GameMatch::with(['matchday:id,name', 'season:id,name,sport_id', 'participants' => ParticipantLabel::eagerLoad()])
            ->whereIn('season_id', $seasonIds)
            ->where('status', MatchStatus::Pending->value)
            ->whereHas('season.sport', fn ($s) => $s->whereHas('eventTypes'))
            ->orderBy('scheduled_at')->get()
            ->map(fn ($m) => ['id' => $m->id, 'label' => $this->matchLabel($m)]);
    }

    public function formMatch(): ?GameMatch
    {
        return ctype_digit((string) $this->form['match_id']) ? GameMatch::with('season')->find($this->form['match_id']) : null;
    }

    public function playerOptions(): Collection
    {
        $match = $this->formMatch();

        return $match ? app(MatchEventService::class)->eligiblePlayers($match) : collect();
    }

    public function eventTypeOptions(): Collection
    {
        $match = $this->formMatch();

        return $match ? app(MatchEventService::class)->eventTypesFor($match) : collect();
    }

    public function matchLabel(GameMatch $m): string
    {
        $names = $m->participants->map(fn ($p) => ParticipantLabel::for($p));

        return ($names->count() === 2 ? $names->join(' vs ') : $names->count() . ' participantes')
            . " — {$m->season->name} / {$m->matchday->name}";
    }

    public function create(): void
    {
        $this->authorize('create', MatchEvent::class);
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $event = MatchEvent::findOrFail($id);
        $this->authorize('update', $event);

        $this->editingId = $event->id;
        $this->form = [
            'match_id' => $event->match_id,
            'season_team_player_id' => $event->season_team_player_id,
            'event_type_id' => $event->event_type_id,
            'minute' => $event->minute ?? '',
        ];
        $this->showModal = true;
    }

    public function save(MatchEventService $service): void
    {
        $data = $this->validate($this->rules())['form'];
        $data['minute'] = in_array($data['minute'], ['', null], true) ? null : (int) $data['minute'];

        if ($this->editingId) {
            $event = MatchEvent::findOrFail($this->editingId);
            $this->authorize('update', $event);
            $service->update($event, $data, auth()->user());
        } else {
            $this->authorize('create', MatchEvent::class);
            $service->register($data, auth()->user());
        }

        // Mismo cierre que el resto de CRUDs. Antes, al crear, el modal se
        // quedaba abierto a proposito con el partido preseleccionado, y el
        // usuario lo percibia como que "no se guardo / no se cierra".
        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Incidencia guardada.');
    }

    public function delete(int $id, MatchEventService $service): void
    {
        $event = MatchEvent::findOrFail($id);
        $this->authorize('delete', $event);

        $result = $service->delete($event, auth()->user());

        $this->dispatch('toast', type: $result === true ? 'success' : 'error',
            message: $result === true ? 'Incidencia eliminada.' : $result);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $user = auth()->user();

        $query = $this->listingQuery();

        return view('livewire.match-events.index', [
            'events' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => SeasonAccess::manageableSeasons($user, includeClosed: true),
            'filterEventTypes' => EventType::with('sport:id,name')->orderBy('sport_id')->orderBy('name')->get(),
        ]);
    }

    /** Listado filtrado y ordenado: lo comparten render() y la exportacion a Excel. */
    private function listingQuery(): Builder
    {
        $user = auth()->user();
        $term = trim($this->search);

        return MatchEvent::query()
            ->select('match_events.*')
            ->addSelect(['player_last_name' => Player::select('players.last_name')
                ->join('season_team_player', 'season_team_player.player_id', '=', 'players.id')
                ->whereColumn('season_team_player.id', 'match_events.season_team_player_id')])
            ->unless($user->hasRole('admin'), fn ($q) => $q->whereHas('match.season.gamesEdition', fn ($t) => $t->where('organizer_id', $user->id)))
            ->when($term !== '', fn ($q) => $q->whereHas('seasonTeamPlayer.player', fn ($p) => $p->where('last_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%")))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->whereHas('match', fn ($m) => $m->where('season_id', (int) $this->seasonFilter)))
            ->when(ctype_digit($this->eventTypeFilter), fn ($q) => $q->where('event_type_id', (int) $this->eventTypeFilter))
            ->with([
                'eventType:id,name,code', 'seasonTeamPlayer.player:id,first_name,last_name', 'seasonTeamPlayer.seasonTeam.team:id,name',
                'match:id,season_id,matchday_id,status,scheduled_at', 'match.season:id,name,games_edition_id', 'match.season.gamesEdition:id,organizer_id',
                'match.matchday:id,name', 'match.participants' => ParticipantLabel::eagerLoad(),
            ])
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('match_events.id');
    }

    protected function authorizeExport(): void
    {
        $this->authorize('viewAny', MatchEvent::class);
    }

    protected function exportTitle(): string
    {
        return 'Incidencias';
    }

    protected function exportRows(): iterable
    {
        return $this->listingQuery()->get();
    }

    protected function exportColumns(): array
    {
        return [
            'Min.' => fn (MatchEvent $e) => $e->minute,
            'Incidencia' => fn (MatchEvent $e) => $e->eventType->name,
            'Apellidos' => fn (MatchEvent $e) => $e->seasonTeamPlayer->player->last_name,
            'Nombres' => fn (MatchEvent $e) => $e->seasonTeamPlayer->player->first_name,
            'Delegación' => fn (MatchEvent $e) => $e->seasonTeamPlayer->seasonTeam->team->name,
            'Partido' => fn (MatchEvent $e) => $this->matchLabel($e->match),
            'Estado del partido' => fn (MatchEvent $e) => $e->match->status->label(),
            'Registrada' => fn (MatchEvent $e) => $e->created_at->format('d/m/Y H:i'),
        ];
    }

    protected function sortableFields(): array
    {
        return ['created_at', 'minute', 'player_last_name'];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = ['match_id' => '', 'season_team_player_id' => '', 'event_type_id' => '', 'minute' => ''];
        $this->resetErrorBag();
    }

    /** Reglas de negocio (tipo del deporte, participante participante): MatchEventService. */
    private function rules(): array
    {
        return [
            'form.match_id' => ['required', 'integer', Rule::exists('matches', 'id')],
            'form.season_team_player_id' => ['required', 'integer', Rule::exists('season_team_player', 'id')],
            'form.event_type_id' => ['required', 'integer', Rule::exists('event_types', 'id')],
            'form.minute' => ['nullable', 'integer', 'between:0,200'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.match_id' => 'partido',
            'form.season_team_player_id' => 'participante',
            'form.event_type_id' => 'tipo de incidencia',
            'form.minute' => 'minuto',
        ];
    }
}
