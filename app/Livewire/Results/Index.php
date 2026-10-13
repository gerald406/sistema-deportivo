<?php

declare(strict_types=1);

namespace App\Livewire\Results;

use App\Enums\MatchStatus;
use App\Enums\SportFormatType;
use App\Livewire\Concerns\ExportsExcel;
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
    use ExportsExcel, LimitsPerPage, Sortable, WithPagination;

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

    /** Desempate de un partido de llave empatado: via ('' = sin desempate), lado ganador y marcador opcional. */
    public string $tiebreakType = '';

    public string $tiebreakWinner = '';

    public string $tiebreakHome = '';

    public string $tiebreakAway = '';

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

    /** Resultado en texto plano para la exportacion: misma lectura que la columna de la vista. */
    private function resultText(GameMatch $match): string
    {
        if ($match->status === MatchStatus::Walkover) {
            $absent = $match->participants->firstWhere('position', 2);

            return 'W.O. — no se presentó '.($absent ? ParticipantLabel::for($absent) : '?');
        }

        if ($match->status !== MatchStatus::Played) {
            return '';
        }

        if ($this->formatOf($match) === SportFormatType::HeadToHead) {
            return (int) $match->participants[0]->result_value.' - '.(int) $match->participants[1]->result_value;
        }

        return $match->participants->whereNotNull('position')->sortBy('position')->take(3)
            ->map(fn ($p) => "{$p->position}° ".ParticipantLabel::for($p).' ('.rtrim(rtrim((string) $p->result_value, '0'), '.').')')
            ->join('; ');
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
     * Cargar el resultado: solo quien gestiona la competencia (organizador del
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
        $match = $this->visibleMatches()->with('season.gamesEdition', 'season.sport', 'discipline')->findOrFail($id);
        abort_unless($this->canManage($match), 403);

        // Partido de llave que aun espera al ganador de la ronda anterior.
        if ($this->formatOf($match) === SportFormatType::HeadToHead && $match->participants()->count() < 2) {
            $this->dispatch('toast', type: 'info', message: 'El partido aún no tiene a sus dos participantes: se completa al cerrar la ronda anterior.');

            return;
        }

        $this->editingId = $match->id;
        $this->tiebreakType = '';
        $this->tiebreakWinner = '';
        $this->tiebreakHome = '';
        $this->tiebreakAway = '';
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
            // Solo se envia si se eligio una via: ResultService decide si corresponde.
            'tiebreak_type' => $this->walkover === '' && $this->tiebreakType !== '' ? $this->tiebreakType : null,
            'tiebreak_winner_side' => $this->tiebreakWinner ?: null,
            'tiebreak_home' => $this->tiebreakHome,
            'tiebreak_away' => $this->tiebreakAway,
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

        $query = $this->listingQuery();

        return view('livewire.results.index', [
            'matches' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => $service->visibleSeasons($user),
            'statusOptions' => MatchStatus::cases(),
        ]);
    }

    /** Listado filtrado y ordenado: lo comparten render() y la exportacion a Excel. */
    private function listingQuery(): Builder
    {
        $user = auth()->user();
        $term = trim($this->search);
        $service = app(GameMatchService::class);

        return $service->scopeVisibleTo(GameMatch::query(), $user)
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
    }

    protected function authorizeExport(): void
    {
        $this->authorize('viewAny', EventParticipant::class);
    }

    protected function exportTitle(): string
    {
        return 'Resultados';
    }

    protected function exportRows(): iterable
    {
        return $this->listingQuery()->get();
    }

    protected function exportColumns(): array
    {
        return [
            'Partido / prueba' => fn (GameMatch $m) => $this->formatOf($m) === SportFormatType::HeadToHead && $m->participants->count() === 2
                ? $m->participants->map(fn ($p) => ParticipantLabel::for($p))->join(' vs ')
                : ($m->discipline?->name ?? $m->season->sport->name)." ({$m->participants_count})",
            'Competencia' => fn (GameMatch $m) => $m->season->name,
            'Jornada' => fn (GameMatch $m) => $m->matchday->name,
            'Fecha' => fn (GameMatch $m) => $m->scheduled_at?->format('d/m/Y H:i'),
            'Resultado' => fn (GameMatch $m) => $this->resultText($m),
            'Estado' => fn (GameMatch $m) => $m->status->label(),
        ];
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
