<?php

declare(strict_types=1);

namespace App\Livewire\Matches;

use App\Enums\MatchModality;
use App\Enums\MatchStatus;
use App\Enums\RoundType;
use App\Enums\SeasonTeamPlayerStatus;
use App\Enums\SportFormatType;
use App\Livewire\Concerns\LimitsPerPage;
use App\Livewire\Concerns\Sortable;
use App\Models\Discipline;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Venue;
use App\Services\GameMatchService;
use App\Support\SeasonAccess;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Partidos')]
class Index extends Component
{
    use LimitsPerPage, Sortable, WithPagination;

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
        'season_id' => '',
        'matchday_id' => '',
        'discipline_id' => '',
        'venue_id' => '',
        'round_type' => 'regular',
        'modality' => '',
        'scheduled_at' => '',
        'status' => 'pending',
    ];

    /** Enfrentamientos: 'teams' (season_team) o 'players' (season_team_player). */
    public string $participantMode = 'teams';

    public string $homeId = '';

    public string $awayId = '';

    /**
     * Individual / postas: filas [id, lane].
     *
     * @var array<int, array{id: string|int, lane: string}>
     */
    public array $entries = [];

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

    public function updatedFormSeasonId(): void
    {
        $this->form['matchday_id'] = '';
        $this->form['discipline_id'] = '';
        $this->resetParticipants();
    }

    public function updatedFormDisciplineId(): void
    {
        $this->resetParticipants();
    }

    public function updatedParticipantMode(): void
    {
        $this->homeId = '';
        $this->awayId = '';
    }

    // ---------------- opciones del formulario ----------------

    public function roundOptions(): array
    {
        return RoundType::cases();
    }

    public function modalityOptions(): array
    {
        return MatchModality::cases();
    }

    public function seasonOptions(): Collection
    {
        return SeasonAccess::manageableSeasons(auth()->user());
    }

    public function formSeason(): ?Season
    {
        return ctype_digit((string) $this->form['season_id'])
            ? Season::with('sport', 'tournament')->find($this->form['season_id'])
            : null;
    }

    public function matchdayOptions(): Collection
    {
        return ctype_digit((string) $this->form['season_id'])
            ? Matchday::where('season_id', $this->form['season_id'])->orderBy('start_date')->orderBy('id')->get(['id', 'name', 'is_completed'])
            : collect();
    }

    public function disciplineOptions(): Collection
    {
        $season = $this->formSeason();

        return $season ? Discipline::where('sport_id', $season->sport_id)->orderBy('name')->get(['id', 'name', 'format_type']) : collect();
    }

    public function venueOptions(): Collection
    {
        return Venue::where('is_active', true)
            ->when($this->form['venue_id'] !== '', fn ($q) => $q->orWhere('id', $this->form['venue_id']))
            ->orderBy('name')->get(['id', 'name']);
    }

    /** Formato efectivo del partido que se esta armando (null si falta la temporada). */
    public function format(): ?SportFormatType
    {
        $season = $this->formSeason();

        if (! $season) {
            return null;
        }

        $discipline = ctype_digit((string) $this->form['discipline_id']) ? Discipline::find($this->form['discipline_id']) : null;

        return app(GameMatchService::class)->formatFor($season, $discipline);
    }

    public function teamOptions(): Collection
    {
        return ctype_digit((string) $this->form['season_id'])
            ? SeasonTeam::with('team:id,name')->where('season_id', $this->form['season_id'])->where('is_active', true)->get()
                ->map(fn ($st) => ['id' => $st->id, 'label' => $st->team->name])->sortBy('label')->values()
            : collect();
    }

    public function playerOptions(): Collection
    {
        return ctype_digit((string) $this->form['season_id'])
            ? SeasonTeamPlayer::with(['player:id,first_name,last_name', 'seasonTeam.team:id,name'])
                ->where('status', SeasonTeamPlayerStatus::Active->value)
                ->whereHas('seasonTeam', fn ($q) => $q->where('season_id', $this->form['season_id'])->where('is_active', true))
                ->get()
                ->map(fn ($e) => ['id' => $e->id, 'label' => "{$e->player->last_name}, {$e->player->first_name} — {$e->seasonTeam->team->name}"])
                ->sortBy('label')->values()
            : collect();
    }

    public function addEntry(): void
    {
        $this->entries[] = ['id' => '', 'lane' => ''];
    }

    public function removeEntry(int $index): void
    {
        unset($this->entries[$index]);
        $this->entries = array_values($this->entries);
    }

    // ---------------- CRUD ----------------

    public function create(): void
    {
        $this->authorize('create', GameMatch::class);

        $this->resetForm();

        if (ctype_digit($this->seasonFilter) && $this->seasonOptions()->contains('id', (int) $this->seasonFilter)) {
            $this->form['season_id'] = (int) $this->seasonFilter;

            if (ctype_digit($this->matchdayFilter)) {
                $this->form['matchday_id'] = (int) $this->matchdayFilter;
            }
        }

        $this->showModal = true;
    }

    public function edit(int $id, GameMatchService $service): void
    {
        $match = GameMatch::findOrFail($id);
        $this->authorize('update', $match);

        $this->editingId = $match->id;
        $this->form = [
            'season_id' => $match->season_id,
            'matchday_id' => $match->matchday_id,
            'discipline_id' => $match->discipline_id ?? '',
            'venue_id' => $match->venue_id ?? '',
            'round_type' => $match->round_type->value,
            'modality' => $match->modality?->value ?? '',
            'scheduled_at' => $match->scheduled_at?->format('Y-m-d\TH:i') ?? '',
            'status' => $match->status->value,
        ];

        $this->resetParticipants();
        $current = $service->currentParticipants($match);

        if ($this->format() === SportFormatType::HeadToHead) {
            $this->participantMode = ($current[0]['type'] ?? 'season_team') === 'season_team_player' ? 'players' : 'teams';
            $this->homeId = (string) (collect($current)->firstWhere('side', 'home')['id'] ?? '');
            $this->awayId = (string) (collect($current)->firstWhere('side', 'away')['id'] ?? '');
        } else {
            $this->entries = array_map(fn ($p) => ['id' => (string) $p['id'], 'lane' => (string) ($p['lane'] ?? '')], $current) ?: [['id' => '', 'lane' => '']];
        }

        $this->showModal = true;
    }

    public function save(GameMatchService $service): void
    {
        $data = $this->validate($this->rules())['form'];
        $participants = $this->participantsPayload();
        $actor = auth()->user();

        if ($this->editingId) {
            $match = GameMatch::findOrFail($this->editingId);
            $this->authorize('update', $match);
            $service->update($match, $data, $participants, $actor);
        } else {
            $this->authorize('create', GameMatch::class);
            $service->register($data, $participants, $actor);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Partido guardado correctamente.');
    }

    public function delete(int $id, GameMatchService $service): void
    {
        $match = GameMatch::findOrFail($id);
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
        $model = $p->participant;

        return match (true) {
            $model instanceof SeasonTeam => $model->team?->name ?? '¿?',
            $model instanceof SeasonTeamPlayer => $model->player ? "{$model->player->last_name}, {$model->player->first_name}" : '¿?',
            default => '(participante eliminado)',
        };
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
                    : $m->whereHas('player', fn ($pl) => $pl->where('last_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%")))))
            ->when(ctype_digit($this->seasonFilter), fn ($q) => $q->where('season_id', (int) $this->seasonFilter))
            ->when(ctype_digit($this->matchdayFilter), fn ($q) => $q->where('matchday_id', (int) $this->matchdayFilter))
            ->when(MatchStatus::tryFrom($this->matchStatusFilter), fn ($q, $s) => $q->where('status', $s->value))
            ->with([
                'season:id,name,tournament_id,sport_id', 'season.tournament:id,name,organizer_id', 'season.sport:id,name,format_type',
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

        $filterSeasons = SeasonAccess::manageableSeasons($user, includeClosed: true);

        return view('livewire.matches.index', [
            'matches' => $query->paginate($this->perPageLimit()),
            'filterSeasons' => $filterSeasons,
            'filterMatchdays' => ctype_digit($this->seasonFilter)
                ? Matchday::where('season_id', (int) $this->seasonFilter)->orderBy('start_date')->orderBy('id')->get(['id', 'name'])
                : collect(),
            'statusOptions' => MatchStatus::cases(),
        ]);
    }

    protected function sortableFields(): array
    {
        return ['scheduled_at', 'status', 'round_type', 'participants_count'];
    }

    /** Traduce el estado del formulario al formato de GameMatchService. */
    private function participantsPayload(): array
    {
        $format = $this->format();

        if ($format === SportFormatType::HeadToHead) {
            $type = $this->participantMode === 'players' ? 'season_team_player' : 'season_team';

            return array_values(array_filter([
                ctype_digit($this->homeId) ? ['type' => $type, 'id' => (int) $this->homeId, 'side' => 'home', 'lane' => null] : null,
                ctype_digit($this->awayId) ? ['type' => $type, 'id' => (int) $this->awayId, 'side' => 'away', 'lane' => null] : null,
            ]));
        }

        $type = $format === SportFormatType::TeamRelay ? 'season_team' : 'season_team_player';

        return collect($this->entries)
            ->filter(fn ($e) => ctype_digit((string) $e['id']))
            ->map(fn ($e) => ['type' => $type, 'id' => (int) $e['id'], 'side' => null, 'lane' => trim((string) $e['lane']) ?: null])
            ->values()->all();
    }

    private function resetParticipants(): void
    {
        $this->participantMode = 'teams';
        $this->homeId = '';
        $this->awayId = '';
        $this->entries = [['id' => '', 'lane' => '']];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'season_id' => '',
            'matchday_id' => '',
            'discipline_id' => '',
            'venue_id' => '',
            'round_type' => 'regular',
            'modality' => '',
            'scheduled_at' => '',
            'status' => 'pending',
        ];
        $this->resetParticipants();
        $this->resetErrorBag();
    }

    /** Reglas de negocio (formato, participantes, trigger): GameMatchService. */
    private function rules(): array
    {
        return [
            'form.season_id' => ['required', 'integer', Rule::exists('seasons', 'id')],
            'form.matchday_id' => ['required', 'integer', Rule::exists('matchdays', 'id')],
            'form.discipline_id' => ['nullable', 'integer', Rule::exists('disciplines', 'id')],
            'form.venue_id' => ['nullable', 'integer', Rule::exists('venues', 'id')],
            'form.round_type' => ['required', Rule::enum(RoundType::class)],
            'form.modality' => ['nullable', Rule::enum(MatchModality::class)],
            'form.scheduled_at' => ['nullable', 'date'],
            'form.status' => ['required', Rule::in([MatchStatus::Pending->value, MatchStatus::Canceled->value, MatchStatus::Played->value, MatchStatus::Walkover->value])],
            'participantMode' => ['required', Rule::in(['teams', 'players'])],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.season_id' => 'temporada',
            'form.matchday_id' => 'jornada',
            'form.discipline_id' => 'disciplina',
            'form.venue_id' => 'sede',
            'form.scheduled_at' => 'fecha y hora',
        ];
    }
}
