<?php

declare(strict_types=1);

namespace App\Livewire\Matches;

use App\Enums\MatchStatus;
use App\Livewire\Concerns\ExportsExcel;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Venue;
use App\Services\GameMatchService;
use App\Support\ParticipantLabel;
use App\Support\SeasonAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Programacion de partidos (D24). Los partidos nacen en /fases al generar
 * la llave (BracketService), con sus participantes; aqui solo se programan:
 * jornada, fecha y hora, sede y estado (GameMatchService::schedule()).
 * Competencia, disciplina, ronda y participantes se muestran sin editar.
 */
#[Layout('layouts.admin')]
#[Title('Partidos')]
class Index extends Component
{
    use ExportsExcel, LimitsPerPage, Sortable, WithPagination;

    public string $search = '';

    public string $seasonFilter = '';

    public string $matchdayFilter = '';

    /** all | pending | played | walkover | canceled */
    public string $matchStatusFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'scheduled_at';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [
        'matchday_id' => '',
        'venue_id' => '',
        'scheduled_at' => '',
        'status' => 'pending',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', GameMatch::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSeasonFilter(): void
    {
        $this->matchdayFilter = '';
        $this->resetPage();
    }

    public function updatingMatchdayFilter(): void
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

    // ---------------- opciones del formulario ----------------

    /** Partido en edicion, con lo que viene de la llave (solo lectura en el modal). */
    public function editingMatch(): ?GameMatch
    {
        return $this->editingId
            ? $this->findVisibleMatch($this->editingId)->load([
                'season:id,name,games_edition_id,sport_id,status', 'season.gamesEdition:id,name', 'season.sport:id,name',
                'discipline:id,name', 'stage:id,name',
                'participants' => fn ($q) => $q->orderByRaw("FIELD(side, 'home', 'away')")->orderBy('id')
                    ->with(['participant' => fn (MorphTo $m) => $m->morphWith([
                        SeasonTeam::class => ['team:id,name'],
                        SeasonTeamPlayer::class => ['player:id,first_name,last_name'],
                    ])]),
            ])
            : null;
    }

    /**
     * Jornadas de la competencia del partido en edicion. La competencia se
     * busca entre las que el usuario gestiona (incluye Cerradas para poder
     * abrir un partido existente).
     */
    public function matchdayOptions(): Collection
    {
        $season = $this->editingSeason();

        return $season
            ? Matchday::where('season_id', $season->id)->orderBy('start_date')->orderBy('id')->get(['id', 'name', 'is_completed'])
            : collect();
    }

    public function venueOptions(): Collection
    {
        return Venue::where('is_active', true)
            ->when($this->form['venue_id'] !== '', fn ($q) => $q->orWhere('id', $this->form['venue_id']))
            ->orderBy('name')->get(['id', 'name']);
    }

    // ---------------- programacion ----------------

    public function edit(int $id): void
    {
        $match = $this->findVisibleMatch($id);
        $this->authorize('update', $match);

        $this->editingId = $match->id;
        $this->form = [
            'matchday_id' => $match->matchday_id,
            'venue_id' => $match->venue_id ?? '',
            'scheduled_at' => $match->scheduled_at?->format('Y-m-d\TH:i') ?? '',
            'status' => $match->status->value,
        ];
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(GameMatchService $service): void
    {
        $match = $this->findVisibleMatch($this->editingId ?? 0);
        $this->authorize('update', $match);

        $service->schedule($match, $this->validate($this->rules())['form'], auth()->user());

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Partido programado correctamente.');
    }

    public function delete(int $id, GameMatchService $service): void
    {
        $match = $this->findVisibleMatch($id);
        $this->authorize('delete', $match);

        $result = $service->delete($match);

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Partido eliminado.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    /** Nombre legible de un participante ya cargado con su morph. */
    public function participantLabel(EventParticipant $p): string
    {
        return ParticipantLabel::for($p);
    }

    public function render(GameMatchService $service)
    {
        $user = auth()->user();

        $query = $this->listingQuery();

        $filterSeasons = $service->visibleSeasons($user);

        return view('livewire.matches.index', [
            'matches' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => $filterSeasons,
            // Jornadas solo de una competencia visible (seasonFilter viene del navegador).
            'filterMatchdays' => ctype_digit($this->seasonFilter) && $filterSeasons->contains('id', (int) $this->seasonFilter)
                ? Matchday::where('season_id', (int) $this->seasonFilter)->orderBy('start_date')->orderBy('id')->get(['id', 'name'])
                : collect(),
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
                    : $m->whereHas('player', fn ($pl) => $pl->where('last_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%")))))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->where('season_id', (int) $this->seasonFilter))
            ->when(ctype_digit($this->matchdayFilter), fn ($q) => $q->where('matchday_id', (int) $this->matchdayFilter))
            ->when(MatchStatus::tryFrom($this->matchStatusFilter), fn ($q, $s) => $q->where('status', $s->value))
            ->with([
                'season:id,name,games_edition_id,sport_id', 'season.gamesEdition:id,name,organizer_id', 'season.sport:id,name,format_type',
                'matchday:id,name', 'discipline:id,name,format_type', 'venue:id,name',
                'participants' => fn ($q) => $q->orderByRaw("FIELD(side, 'home', 'away')")->orderBy('id')
                    ->with(['participant' => fn (MorphTo $m) => $m->morphWith([
                        SeasonTeam::class => ['team:id,name'],
                        SeasonTeamPlayer::class => ['player:id,first_name,last_name'],
                    ])]),
            ])
            ->withCount('participants')
            ->orderBy($this->sortColumn(), $this->sortOrder())
            ->orderBy('id');
    }

    protected function authorizeExport(): void
    {
        $this->authorize('viewAny', GameMatch::class);
    }

    protected function exportTitle(): string
    {
        return 'Partidos';
    }

    protected function exportRows(): iterable
    {
        return $this->listingQuery()->get();
    }

    protected function exportColumns(): array
    {
        return [
            'Fecha' => fn (GameMatch $m) => $m->scheduled_at?->format('d/m/Y H:i'),
            'Participantes' => fn (GameMatch $m) => $m->participants->map(fn ($p) => ParticipantLabel::for($p))
                ->join($m->participants->count() === 2 ? ' vs ' : '; '),
            'Prueba' => fn (GameMatch $m) => $m->discipline?->name,
            'Competencia' => fn (GameMatch $m) => $m->season->name,
            'Jornada' => fn (GameMatch $m) => $m->matchday->name,
            'Olimpiada' => fn (GameMatch $m) => $m->season->gamesEdition->name,
            'Deporte' => fn (GameMatch $m) => $m->season->sport->name,
            'Ronda' => fn (GameMatch $m) => $m->round_type->label(),
            'Sede' => fn (GameMatch $m) => $m->venue?->name,
            'Estado' => fn (GameMatch $m) => $m->status->label(),
        ];
    }

    protected function sortableFields(): array
    {
        return ['scheduled_at', 'status', 'round_type', 'participants_count'];
    }

    /** Competencia del partido en edicion, solo si el usuario la gestiona. */
    private function editingSeason(): ?Season
    {
        $seasonId = $this->editingId ? GameMatch::whereKey($this->editingId)->value('season_id') : null;

        return $seasonId
            ? SeasonAccess::manageableSeasons(auth()->user(), includeClosed: true)->firstWhere('id', $seasonId)
            : null;
    }

    /**
     * Ids que llegan del navegador se buscan acotados al alcance del
     * usuario (GameMatchService::scopeVisibleTo): un partido ajeno da 404
     * antes de llegar a la Policy.
     */
    private function findVisibleMatch(int $id): GameMatch
    {
        return app(GameMatchService::class)
            ->scopeVisibleTo(GameMatch::query(), auth()->user())
            ->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'matchday_id' => '',
            'venue_id' => '',
            'scheduled_at' => '',
            'status' => 'pending',
        ];
        $this->resetErrorBag();
    }

    /** Que la jornada sea de la competencia, las fechas y el estado: GameMatchService::schedule(). */
    private function rules(): array
    {
        return [
            'form.matchday_id' => ['required', 'integer', Rule::exists('matchdays', 'id')],
            'form.venue_id' => ['nullable', 'integer', Rule::exists('venues', 'id')],
            'form.scheduled_at' => ['nullable', 'date'],
            'form.status' => ['required', Rule::in(array_column(MatchStatus::cases(), 'value'))],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.matchday_id' => 'jornada',
            'form.venue_id' => 'sede',
            'form.scheduled_at' => 'fecha y hora',
        ];
    }
}
