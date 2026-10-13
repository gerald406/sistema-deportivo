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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
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
    /** En la URL (?estado=played) para que la bitacora enlace directo a los partidos reabribles. */
    #[Url(as: 'estado')]
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
            ? $this->visibleMatches()->with(['season.sport', 'discipline', 'matchday', 'periods', 'participants' => ParticipantLabel::eagerLoad()])->find($this->editingId)
            : null;
    }

    /** Marcador derivado de parciales (null si se ingresa a mano). */
    public function derivedScore(): ?array
    {
        $match = $this->editingMatch();

        return $match ? app(ResultService::class)->scoreFromPeriods($match) : null;
    }

    /**
     * Cargar el resultado: solo quien gestiona la temporada (organizador del
     * olimpiada o admin). El delegado ve /resultados en solo lectura.
     */
    public function canManage(GameMatch $match): bool
    {
        return SeasonAccess::manages(auth()->user(), $match->season);
    }

    public function canReopen(GameMatch $match): bool
    {
        return in_array($match->status, [MatchStatus::Played, MatchStatus::Walkover], true)
            && Gate::allows('reopenMatches', $match->season);
    }

    public function openResult(int $id): void
    {
        $match = $this->visibleMatches()->with('season.gamesEdition')->findOrFail($id);
        abort_unless($this->canManage($match), 403);

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
        // ResultService::close() revalida SeasonAccess::manages().
        $match = $this->visibleMatches()->findOrFail($this->editingId);

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
        $match = $this->visibleMatches()->with('season.gamesEdition')->findOrFail($id);
        abort_unless($this->canReopen($match), 403);

        $this->reopeningId = $match->id;
        $this->reopenReason = '';
        $this->resetErrorBag();
        $this->showReopenModal = true;
    }

    /**
     * Reabre el resultado (ResultService::reopen() revalida el permiso y
     * deja el registro en MatchReopenLog) y abre de inmediato el modal de
     * resultado para volver a cargar el marcador. La bitacora /reaperturas
     * es solo lectura: la accion vive aqui.
     */
    public function reopen(ResultService $service): void
    {
        $match = $this->visibleMatches()->findOrFail($this->reopeningId);
        $service->reopen($match, $this->reopenReason, auth()->user());

        $this->showReopenModal = false;
        $this->reopeningId = null;
        $this->reopenReason = '';
        $this->dispatch('toast', type: 'success', message: 'Resultado reabierto y registrado en la bitácora. Ya puedes corregir el marcador.');

        $this->openResult($match->id);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->showReopenModal = false;
        $this->editingId = null;
        $this->reopeningId = null;
        $this->resetErrorBag();
    }

    public function render(GameMatchService $service)
    {
        $user = auth()->user();
        $term = trim($this->search);

        $query = $service->scopeVisibleTo(GameMatch::query(), $user)
            ->when($term !== '', fn ($q) => $q->whereHas('participants', fn ($p) => $p->whereHasMorph('participant', [SeasonTeam::class, SeasonTeamPlayer::class],
                fn ($m, string $type) => $type === SeasonTeam::class
                    ? $m->whereHas('team', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                    : $m->whereHas('player', fn ($pl) => $pl->where('last_name', 'like', "%{$term}%")))))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->where('season_id', (int) $this->seasonFilter))
            ->when(MatchStatus::tryFrom($this->matchStatusFilter), fn ($q, $s) => $q->where('status', $s->value))
            ->with([
                'season:id,name,status,games_edition_id,sport_id', 'season.gamesEdition:id,name,organizer_id', 'season.sport:id,name,format_type',
                'discipline:id,name,format_type,better_direction,unit_of_measure', 'matchday:id,name',
                'participants' => ParticipantLabel::eagerLoad(),
            ])
            ->withCount('participants')
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('id');

        return view('livewire.results.index', [
            'matches' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => $service->visibleSeasons($user),
            'statusOptions' => MatchStatus::cases(),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['scheduled_at', 'status', 'participants_count'];
    }

    /**
     * Ids que llegan del navegador (editingId, reopeningId, parametros) se
     * buscan acotados al alcance del usuario (GameMatchService::scopeVisibleTo):
     * un partido ajeno da 404 antes de cualquier chequeo de permiso.
     */
    private function visibleMatches(): Builder
    {
        return app(GameMatchService::class)->scopeVisibleTo(GameMatch::query(), auth()->user());
    }
}
