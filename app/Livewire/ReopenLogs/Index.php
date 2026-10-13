<?php

declare(strict_types=1);

namespace App\Livewire\ReopenLogs;

use App\Enums\MatchStatus;
use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\EventParticipant;
use App\Models\MatchReopenLog;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\User;
use App\Support\ParticipantLabel;
use App\Support\SeasonAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vista de SOLO LECTURA de la auditoria de reaperturas. Los registros los
 * crea ResultService::reopen() y no se editan ni se borran.
 */
#[Layout('layouts.admin')]
#[Title('Reaperturas de partidos')]
class Index extends Component
{
    use ExportsExcel, LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    public string $seasonFilter = '';

    public string $userFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 25;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public ?int $viewingId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', MatchReopenLog::class);
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'seasonFilter', 'userFilter', 'dateFrom', 'dateTo', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function show(int $id): void
    {
        $this->viewingId = $this->baseQuery()->findOrFail($id)->id;
    }

    public function closeDetail(): void
    {
        $this->viewingId = null;
    }

    public function viewing(): ?MatchReopenLog
    {
        return $this->viewingId ? MatchReopenLog::with(['user:id,name', 'match.matchday', 'match.season'])->find($this->viewingId) : null;
    }

    /**
     * Nombres legibles de los participantes del snapshot (pueden haber
     * cambiado o no existir ya: se resuelven por tipo/id guardados).
     *
     * @return array<int, array{label: string, side: ?string, result: mixed, position: mixed}>
     */
    public function snapshotParticipants(MatchReopenLog $log): array
    {
        return collect($log->previous_state['participants'] ?? [])->map(function ($p) {
            $ep = new EventParticipant(['participant_type' => $p['type'], 'participant_id' => $p['participant_id']]);
            $ep->setRelation('participant', match ($p['type']) {
                'season_team' => SeasonTeam::with('team:id,name')->find($p['participant_id']),
                'season_team_player' => SeasonTeamPlayer::with('player:id,first_name,last_name')->find($p['participant_id']),
                default => null,
            });

            return ['label' => ParticipantLabel::for($ep), 'side' => $p['side'], 'result' => $p['result_value'], 'position' => $p['position']];
        })->all();
    }

    public function userOptions(): Collection
    {
        return User::whereIn('id', MatchReopenLog::select('user_id'))->orderBy('name')->get(['id', 'name']);
    }

    public function render()
    {
        $user = auth()->user();

        $query = $this->listingQuery();

        return view('livewire.reopen-logs.index', [
            'logs' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => SeasonAccess::manageableSeasons($user, includeClosed: true),
        ]);
    }

    /** Listado filtrado y ordenado: lo comparten render() y la exportacion a Excel. */
    private function listingQuery(): Builder
    {
        $term = trim($this->search);

        return $this->baseQuery()
            ->when($term !== '', fn ($q) => $q->where('reason', 'like', "%{$term}%"))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->whereHas('match', fn ($m) => $m->where('season_id', (int) $this->seasonFilter)))
            ->when(ctype_digit($this->userFilter), fn ($q) => $q->where('user_id', (int) $this->userFilter))
            ->when(strtotime($this->dateFrom) !== false && $this->dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when(strtotime($this->dateTo) !== false && $this->dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->with([
                'user:id,name', 'match:id,season_id,matchday_id,status', 'match.matchday:id,name',
                'match.season:id,name,games_edition_id', 'match.season.gamesEdition:id,name',
                'match.participants' => ParticipantLabel::eagerLoad(),
            ])
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderByDesc('id');
    }

    protected function authorizeExport(): void
    {
        $this->authorize('viewAny', MatchReopenLog::class);
    }

    protected function exportTitle(): string
    {
        return 'Reaperturas';
    }

    protected function exportRows(): iterable
    {
        return $this->listingQuery()->get();
    }

    protected function exportColumns(): array
    {
        return [
            'Fecha' => fn (MatchReopenLog $l) => $l->created_at->format('d/m/Y H:i'),
            'Partido' => fn (MatchReopenLog $l) => $l->match?->participants->map(fn ($p) => ParticipantLabel::for($p))->join(' vs '),
            'Olimpiada' => fn (MatchReopenLog $l) => $l->match?->season->gamesEdition->name,
            'Competencia' => fn (MatchReopenLog $l) => $l->match?->season->name,
            'Jornada' => fn (MatchReopenLog $l) => $l->match?->matchday->name,
            'Usuario' => fn (MatchReopenLog $l) => $l->user->name,
            'Estado previo' => fn (MatchReopenLog $l) => MatchStatus::tryFrom($l->previous_status)?->label() ?? $l->previous_status,
            'Sanciones borradas' => fn (MatchReopenLog $l) => $l->suspensions_deleted,
            'Motivo' => fn (MatchReopenLog $l) => $l->reason,
        ];
    }

    protected function sortableFields(): array
    {
        return ['created_at', 'previous_status', 'suspensions_deleted'];
    }

    /**
     * Visibilidad: admin todo; organizador solo reaperturas de partidos de
     * sus olimpiadas. Un log cuyo partido ya se borro (match_id NULL por SET
     * NULL) solo lo ve el admin.
     */
    private function baseQuery()
    {
        $user = auth()->user();

        return MatchReopenLog::query()
            ->unless($user->hasRole('admin'), fn ($q) => $q->whereHas('match.season.gamesEdition', fn ($t) => $t->where('organizer_id', $user->id)));
    }
}
