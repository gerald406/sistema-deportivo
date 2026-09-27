<?php

declare(strict_types=1);

namespace App\Livewire\Periods;

use App\Enums\MatchStatus;
use App\Enums\SportFormatType;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\MatchPeriod;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Services\GameMatchService;
use App\Services\MatchPeriodService;
use App\Support\ParticipantLabel;
use App\Support\SeasonAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Parciales')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    public string $seasonFilter = '';

    /** all | pending | played | walkover | canceled */
    public string $matchStatusFilter = 'pending';

    public int $perPage = 15;

    public string $sortField = 'scheduled_at';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    /** Partido cuyos parciales se editan. */
    public ?int $editingId = null;

    /**
     * Parciales en orden: [0] = periodo 1.
     *
     * @var array<int, array{home: string|int, away: string|int}>
     */
    public array $periods = [];

    public function mount(): void
    {
        $this->authorize('viewAny', MatchPeriod::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSeasonFilter(): void
    {
        $this->resetPage();
    }

    public function updatingMatchStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function editingMatch(): ?GameMatch
    {
        return $this->editingId
            ? GameMatch::with(['matchday:id,name', 'participants' => ParticipantLabel::eagerLoad()])->find($this->editingId)
            : null;
    }

    public function label(EventParticipant $p): string
    {
        return ParticipantLabel::for($p);
    }

    public function edit(int $id, MatchPeriodService $service): void
    {
        $match = app(GameMatchService::class)->queryByFormat(SportFormatType::HeadToHead)->findOrFail($id);
        abort_unless(SeasonAccess::manages(auth()->user(), $match->season), 403);

        $this->editingId = $match->id;
        $this->periods = $match->periods()->orderBy('period_number')->get()
            ->map(fn ($p) => ['home' => $p->home_points, 'away' => $p->away_points])->all()
            ?: [['home' => 0, 'away' => 0]];
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function addPeriod(MatchPeriodService $service): void
    {
        $match = GameMatch::find($this->editingId);

        if ($match && count($this->periods) < $service->maxPeriods($match)) {
            $this->periods[] = ['home' => 0, 'away' => 0];
        }
    }

    public function removePeriod(int $index): void
    {
        unset($this->periods[$index]);
        $this->periods = array_values($this->periods);
    }

    public function save(MatchPeriodService $service): void
    {
        $service->syncPeriods(GameMatch::findOrFail($this->editingId), $this->periods, auth()->user());

        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: 'Parciales guardados.');
    }

    public function clear(int $id, MatchPeriodService $service): void
    {
        $result = $service->clear(GameMatch::findOrFail($id), auth()->user());

        $this->dispatch('toast', type: $result === true ? 'success' : 'error',
            message: $result === true ? 'Parciales borrados.' : $result);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->periods = [];
        $this->resetErrorBag();
    }

    public function render(GameMatchService $matches)
    {
        $user = auth()->user();
        $term = trim($this->search);

        $query = $matches->queryByFormat(SportFormatType::HeadToHead)
            ->unless($user->hasRole('admin'), fn ($q) => $q->whereHas('season.tournament', fn ($t) => $t->where('organizer_id', $user->id)))
            ->when($term !== '', fn ($q) => $q->whereHas('participants', fn ($p) => $p->whereHasMorph('participant', [SeasonTeam::class, SeasonTeamPlayer::class],
                fn ($m, string $type) => $type === SeasonTeam::class
                    ? $m->whereHas('team', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                    : $m->whereHas('player', fn ($pl) => $pl->where('last_name', 'like', "%{$term}%")))))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->where('season_id', (int) $this->seasonFilter))
            ->when(MatchStatus::tryFrom($this->matchStatusFilter), fn ($q, $s) => $q->where('status', $s->value))
            ->with([
                'season:id,name,tournament_id,sport_id', 'season.tournament:id,name,organizer_id', 'season.sport:id,name',
                'matchday:id,name', 'participants' => ParticipantLabel::eagerLoad(),
                'periods' => fn ($q) => $q->orderBy('period_number'),
            ])
            ->withCount('periods')
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('id');

        return view('livewire.periods.index', [
            'matches' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => SeasonAccess::manageableSeasons($user, includeClosed: true),
            'statusOptions' => MatchStatus::cases(),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['scheduled_at', 'periods_count', 'status'];
    }
}
