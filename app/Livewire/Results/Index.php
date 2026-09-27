<?php

declare(strict_types=1);

namespace App\Livewire\Results;

use App\Enums\MatchStatus;
use App\Enums\SportFormatType;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Services\GameMatchService;
use App\Services\ResultService;
use App\Support\ParticipantLabel;
use App\Support\SeasonAccess;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Resultados')]
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

    // --- Modal de carga de resultado ---
    public bool $showModal = false;

    public ?int $editingId = null;

    /** Enfrentamiento: '' (se jugo) | 'home' | 'away' (lado AUSENTE). */
    public string $walkover = '';

    public string $homeScore = '';

    public string $awayScore = '';

    /** Individual/posta: event_participant_id => marca. @var array<int, string> */
    public array $marks = [];

    // --- Modal de reapertura ---
    public bool $showReopenModal = false;

    public ?int $reopeningId = null;

    public string $reopenReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', EventParticipant::class);
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

    public function label(EventParticipant $p): string
    {
        return ParticipantLabel::for($p);
    }

    public function formatOf(GameMatch $match): SportFormatType
    {
        return app(GameMatchService::class)->formatFor($match->season, $match->discipline);
    }

    public function editingMatch(): ?GameMatch
    {
        return $this->editingId
            ? GameMatch::with(['season.sport', 'discipline', 'matchday', 'periods', 'participants' => ParticipantLabel::eagerLoad()])->find($this->editingId)
            : null;
    }

    /** Marcador derivado de parciales (null si se ingresa a mano). */
    public function derivedScore(): ?array
    {
        $match = $this->editingMatch();

        return $match ? app(ResultService::class)->scoreFromPeriods($match) : null;
    }

    public function canReopen(GameMatch $match): bool
    {
        return in_array($match->status, [MatchStatus::Played, MatchStatus::Walkover], true)
            && Gate::allows('reopenMatches', $match->season);
    }

    public function openResult(int $id): void
    {
        $match = GameMatch::with('season.tournament')->findOrFail($id);
        abort_unless(SeasonAccess::manages(auth()->user(), $match->season), 403);

        $this->editingId = $match->id;
        $this->walkover = '';
        $this->homeScore = '';
        $this->awayScore = '';
        $this->marks = $match->participants()->pluck('id')->mapWithKeys(fn ($pid) => [$pid => ''])->all();
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function saveResult(ResultService $service): void
    {
        $match = GameMatch::findOrFail($this->editingId);

        $service->close($match, [
            'walkover' => $this->walkover ?: null,
            'home' => $this->homeScore,
            'away' => $this->awayScore,
            'results' => $this->marks,
        ], auth()->user());

        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: 'Resultado guardado.');
    }

    public function openReopen(int $id): void
    {
        $match = GameMatch::with('season.tournament')->findOrFail($id);
        abort_unless($this->canReopen($match), 403);

        $this->reopeningId = $match->id;
        $this->reopenReason = '';
        $this->resetErrorBag();
        $this->showReopenModal = true;
    }

    public function confirmReopen(ResultService $service): void
    {
        $service->reopen(GameMatch::findOrFail($this->reopeningId), $this->reopenReason, auth()->user());

        $this->showReopenModal = false;
        $this->reopeningId = null;
        $this->reopenReason = '';
        $this->dispatch('toast', type: 'success', message: 'Partido reabierto. La reapertura quedó registrada.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->showReopenModal = false;
        $this->editingId = null;
        $this->reopeningId = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        $user = auth()->user();
        $term = trim($this->search);

        $query = GameMatch::query()
            ->unless($user->hasRole('admin'), fn ($q) => $q->whereHas('season.tournament', fn ($t) => $t->where('organizer_id', $user->id)))
            ->when($term !== '', fn ($q) => $q->whereHas('participants', fn ($p) => $p->whereHasMorph('participant', [SeasonTeam::class, SeasonTeamPlayer::class],
                fn ($m, string $type) => $type === SeasonTeam::class
                    ? $m->whereHas('team', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                    : $m->whereHas('player', fn ($pl) => $pl->where('last_name', 'like', "%{$term}%")))))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->where('season_id', (int) $this->seasonFilter))
            ->when(MatchStatus::tryFrom($this->matchStatusFilter), fn ($q, $s) => $q->where('status', $s->value))
            ->with([
                'season:id,name,status,tournament_id,sport_id', 'season.tournament:id,name,organizer_id', 'season.sport:id,name,format_type',
                'discipline:id,name,format_type,better_direction,unit_of_measure', 'matchday:id,name',
                'participants' => ParticipantLabel::eagerLoad(),
            ])
            ->withCount('participants')
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('id');

        return view('livewire.results.index', [
            'matches' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => SeasonAccess::manageableSeasons($user, includeClosed: true),
            'statusOptions' => MatchStatus::cases(),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['scheduled_at', 'status', 'participants_count'];
    }
}
