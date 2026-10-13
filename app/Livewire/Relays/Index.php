<?php

declare(strict_types=1);

namespace App\Livewire\Relays;

use App\Enums\MatchStatus;
use App\Enums\SeasonTeamPlayerStatus;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\EventLineup;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\SeasonTeam;
use App\Policies\SeasonTeamPolicy;
use App\Models\SeasonTeamPlayer;
use App\Models\Team;
use App\Policies\EventLineupPolicy;
use App\Services\EventLineupService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Relevos de postas')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    /** all | pending | played | walkover | canceled */
    public string $matchStatusFilter = 'pending';

    /** all | missing | loaded */
    public string $lineupFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'match_scheduled_at';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    /** event_participant (equipo en la posta) cuyo orden se edita. */
    public ?int $editingId = null;

    /**
     * Relevistas en orden de tramo: [0] = tramo 1. Ids de season_team_player.
     *
     * @var array<int, string>
     */
    public array $legs = [];

    public function mount(): void
    {
        $this->authorize('viewAny', EventLineup::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingMatchStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingLineupFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function editingParticipant(): ?EventParticipant
    {
        return $this->editingId
            ? EventParticipant::with(['match.discipline', 'match.matchday', 'participant.team'])->find($this->editingId)
            : null;
    }

    /** Relevistas posibles: plantel habilitado del equipo. */
    public function runnerOptions(): Collection
    {
        $participant = $this->editingParticipant();

        return $participant
            ? SeasonTeamPlayer::with('player:id,first_name,last_name')
                ->where('season_team_id', $participant->participant_id)
                ->where('status', SeasonTeamPlayerStatus::Active->value)
                ->get()->sortBy(fn ($e) => $e->player->last_name)->values()
            : collect();
    }

    public function edit(int $id): void
    {
        $participant = app(EventLineupService::class)->relayParticipantsQuery()->findOrFail($id);
        abort_unless(EventLineupPolicy::managesParticipant(auth()->user(), $participant), 403);

        $this->editingId = $participant->id;
        $this->legs = $participant->lineups()->orderBy('leg_order')->pluck('season_team_player_id')->map(fn ($v) => (string) $v)->all()
            ?: array_fill(0, 4, '');
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function addLeg(): void
    {
        if (count($this->legs) < EventLineupService::MAX_LEGS) {
            $this->legs[] = '';
        }
    }

    public function removeLeg(int $index): void
    {
        unset($this->legs[$index]);
        $this->legs = array_values($this->legs);
    }

    public function save(EventLineupService $service): void
    {
        $participant = EventParticipant::findOrFail($this->editingId);

        // Tramos vacios al final se ignoran; uno vacio en medio es un error.
        $legs = $this->legs;
        while ($legs !== [] && trim((string) end($legs)) === '') {
            array_pop($legs);
        }

        foreach ($legs as $i => $id) {
            if (! ctype_digit((string) $id)) {
                $this->addError('legs', 'El tramo ' . ($i + 1) . ' no tiene relevista.');

                return;
            }
        }

        $service->syncLineup($participant, $legs, auth()->user());

        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: 'Orden de relevos guardado.');
    }

    public function clear(int $id, EventLineupService $service): void
    {
        $participant = EventParticipant::findOrFail($id);
        $result = $service->clear($participant, auth()->user());

        $this->dispatch('toast', type: $result === true ? 'success' : 'error',
            message: $result === true ? 'Orden de relevos borrado.' : $result);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->legs = [];
        $this->resetErrorBag();
    }

    public function canManage(EventParticipant $participant): bool
    {
        return EventLineupPolicy::managesParticipant(auth()->user(), $participant);
    }

    public function render(EventLineupService $service)
    {
        $user = auth()->user();
        $term = trim($this->search);

        $query = $service->relayParticipantsQuery()
            ->select('event_participants.*')
            ->addSelect([
                'match_scheduled_at' => GameMatch::select('scheduled_at')->whereColumn('matches.id', 'event_participants.match_id'),
                'team_name' => Team::select('teams.name')->join('season_team', 'season_team.team_id', '=', 'teams.id')
                    ->whereColumn('season_team.id', 'event_participants.participant_id'),
            ])
            // Visibilidad: admin todo; organizador sus olimpiadas; delegado sus delegaciones (titular o suplente).
            ->unless($user->hasRole('admin'), fn ($q) => $q->where(function ($q) use ($user) {
                $q->whereRaw('1 = 0')
                    ->when($user->hasRole('organizador'), fn ($q) => $q->orWhereHas('match.season.gamesEdition', fn ($t) => $t->where('organizer_id', $user->id)))
                    ->when($user->hasRole('delegado'), fn ($q) => $q->orWhereHasMorph('participant', [SeasonTeam::class], SeasonTeamPolicy::delegateConstraint($user)));
            }))
            ->when($term !== '', fn ($q) => $q->whereHasMorph('participant', [SeasonTeam::class], fn ($st) => $st->whereHas('team', fn ($t) => $t->where('name', 'like', "%{$term}%"))))
            ->when(MatchStatus::tryFrom($this->matchStatusFilter), fn ($q, $s) => $q->whereHas('match', fn ($m) => $m->where('status', $s->value)))
            ->when($this->lineupFilter === 'missing', fn ($q) => $q->doesntHave('lineups'))
            ->when($this->lineupFilter === 'loaded', fn ($q) => $q->has('lineups'))
            ->with([
                'match:id,season_id,matchday_id,discipline_id,scheduled_at,status', 'match.discipline:id,name,format_type',
                'match.matchday:id,name', 'match.season:id,name,games_edition_id,sport_id', 'match.season.gamesEdition:id,name,organizer_id',
                'match.season.sport:id,name,format_type', 'participant.team:id,name',
                'lineups' => fn ($q) => $q->orderBy('leg_order')->with('seasonTeamPlayer.player:id,first_name,last_name'),
            ])
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('event_participants.id');

        return view('livewire.relays.index', [
            'participants' => $query->paginate($this->perPageLimit()),
            'statusOptions' => MatchStatus::cases(),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['match_scheduled_at', 'team_name', 'lane_or_board'];
    }
}
