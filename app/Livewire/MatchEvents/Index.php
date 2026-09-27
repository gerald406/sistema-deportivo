<?php

declare(strict_types=1);

namespace App\Livewire\MatchEvents;

use App\Enums\MatchStatus;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\EventType;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Services\MatchEventService;
use App\Support\ParticipantLabel;
use App\Support\SeasonAccess;
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
    use LimitsPerPage, Sortable, WithPagination;

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

    /** Partidos Programados, de temporadas que el usuario gestiona y cuyo deporte tiene tipos de incidencia. */
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

        // Tras guardar se mantiene el partido elegido: lo habitual es cargar
        // varias incidencias seguidas del mismo partido.
        $matchId = $this->editingId ? null : $data['match_id'];
        $this->resetForm();
        $this->showModal = $matchId !== null;
        $this->form['match_id'] = $matchId ?? '';
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
        $term = trim($this->search);

        $query = MatchEvent::query()
            ->select('match_events.*')
            ->addSelect(['player_last_name' => Player::select('players.last_name')
                ->join('season_team_player', 'season_team_player.player_id', '=', 'players.id')
                ->whereColumn('season_team_player.id', 'match_events.season_team_player_id')])
            ->unless($user->hasRole('admin'), fn ($q) => $q->whereHas('match.season.tournament', fn ($t) => $t->where('organizer_id', $user->id)))
            ->when($term !== '', fn ($q) => $q->whereHas('seasonTeamPlayer.player', fn ($p) => $p->where('last_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%")))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->whereHas('match', fn ($m) => $m->where('season_id', (int) $this->seasonFilter)))
            ->when(ctype_digit($this->eventTypeFilter), fn ($q) => $q->where('event_type_id', (int) $this->eventTypeFilter))
            ->with([
                'eventType:id,name,code', 'seasonTeamPlayer.player:id,first_name,last_name', 'seasonTeamPlayer.seasonTeam.team:id,name',
                'match:id,season_id,matchday_id,status,scheduled_at', 'match.season:id,name,tournament_id', 'match.season.tournament:id,organizer_id',
                'match.matchday:id,name', 'match.participants' => ParticipantLabel::eagerLoad(),
            ])
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('match_events.id');

        return view('livewire.match-events.index', [
            'events' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => SeasonAccess::manageableSeasons($user, includeClosed: true),
            'filterEventTypes' => EventType::with('sport:id,name')->orderBy('sport_id')->orderBy('name')->get(),
        ]);
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

    /** Reglas de negocio (tipo del deporte, jugador participante): MatchEventService. */
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
            'form.season_team_player_id' => 'jugador',
            'form.event_type_id' => 'tipo de incidencia',
            'form.minute' => 'minuto',
        ];
    }
}
