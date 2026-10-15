<?php

declare(strict_types=1);

namespace App\Livewire\SeasonStages;

use App\Enums\EditionTeamStatus;
use App\Enums\StageMode;
use App\Livewire\Concerns\ExportsExcel;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Team;
use App\Services\BracketService;
use App\Services\DirectPodiumService;
use App\Services\StageService;
use App\Support\ParticipantLabel;
use App\Support\SeasonAccess;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Fases de la competencia elegida: series, fase final y podio directo.
 * Solo quien programa partidos (matches.manage) y gestiona la competencia.
 *
 * seasonId es publico (viene de ?competencia=): season() SIEMPRE lo busca
 * dentro de las competencias que el usuario gestiona.
 */
#[Layout('layouts.admin')]
#[Title('Fases y llaves')]
class Index extends Component
{
    use ExportsExcel;

    #[Url(as: 'competencia')]
    public string $seasonId = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public array $form = [];

    public bool $showPodium = false;

    public ?int $podiumStageId = null;

    /**
     * Podio en edicion: puesto => lista de filas (varias = puesto compartido).
     *
     * @var array<int, list<array{team_id: int|string, season_team_player_id: int|string}>>
     */
    public array $podium = [];

    public bool $showBracket = false;

    public ?int $bracketStageId = null;

    /**
     * Participantes de la llave en orden (ids de season_team). El orden
     * decide los cruces y quien descansa: los primeros reciben los byes.
     *
     * @var list<int>
     */
    public array $seeds = [];

    public string $seedToAdd = '';

    public function boot(): void
    {
        // El middleware de la ruta no se reaplica en /livewire/update.
        abort_unless(auth()->user()->hasRole('admin') || auth()->user()->can('matches.manage'), 403);
    }

    public function mount(): void
    {
        $this->resetForm();
    }

    /** Competencias que el usuario gestiona (incluye las cerradas, para consultar su podio). */
    public function seasonOptions(): Collection
    {
        return SeasonAccess::manageableSeasons(auth()->user(), includeClosed: true)->sortBy('name')->values();
    }

    public function season(): ?Season
    {
        return ctype_digit($this->seasonId)
            ? $this->seasonOptions()->firstWhere('id', (int) $this->seasonId)
            : null;
    }

    public function modeOptions(): array
    {
        return StageMode::cases();
    }

    /** Series de la olimpiada de la competencia. */
    public function groupOptions(): Collection
    {
        return $this->season()?->gamesEdition->groups()->get(['id', 'name']) ?? collect();
    }

    /**
     * Delegaciones inscritas (y habilitadas) en la olimpiada: las que pueden
     * subir al podio. En la fase de una serie, solo las de esa serie (lo
     * revalida DirectPodiumService).
     */
    public function teamOptions(): Collection
    {
        $season = $this->season();
        $groupId = $season && $this->podiumStageId
            ? SeasonStage::where('season_id', $season->id)->whereKey($this->podiumStageId)->value('group_id')
            : null;

        return $season
            ? Team::whereHas('editionTeams', fn ($q) => $q
                ->where('games_edition_id', $season->games_edition_id)
                ->where('status', EditionTeamStatus::Registered->value)
                ->when($groupId, fn ($q) => $q->where('group_id', $groupId)))
                ->orderBy('name')->get(['id', 'name'])
            : collect();
    }

    /** Plantel de una delegacion en esta competencia, para nombrar al participante premiado. */
    public function rosterOptions(int|string|null $teamId): Collection
    {
        $season = $this->season();

        if (! $season || ! ctype_digit((string) $teamId)) {
            return collect();
        }

        return SeasonTeamPlayer::with('player:id,first_name,last_name')
            ->whereHas('seasonTeam', fn ($q) => $q->where('season_id', $season->id)->where('team_id', (int) $teamId))
            ->get()
            ->sortBy(fn ($entry) => $entry->player->last_name)
            ->values();
    }

    public function createStandard(StageService $service): void
    {
        $created = $service->createStandardStages($this->seasonOrFail(), auth()->user());

        $this->dispatch('toast', type: $created->isEmpty() ? 'info' : 'success', message: $created->isEmpty()
            ? 'La competencia ya tiene fases: no se creó ninguna.'
            : 'Fases creadas: '.$created->pluck('name')->implode(', ').'.');
    }

    /**
     * Fases estandar para todas las competencias activas (y no cerradas)
     * que el usuario gestiona y que aun no tienen fases: sin elegir una a
     * una. StageService revalida cada competencia.
     */
    public function createStandardForAll(StageService $service): void
    {
        $seasons = SeasonAccess::manageableSeasons(auth()->user())->where('is_active', true);

        if ($seasons->isEmpty()) {
            $this->dispatch('toast', type: 'info', message: 'No hay competencias activas: genéralas primero en Competencias.');

            return;
        }

        $result = $service->createMissingStandardStages($seasons, auth()->user());

        $this->dispatch('toast', type: $result['seasons'] === 0 ? 'info' : 'success', message: $result['seasons'] === 0
            ? 'Todas las competencias ya tenían fases: no se creó ninguna.'
            : "Fases creadas en {$result['seasons']} competencias ({$result['stages']} fases). Ya tenían fases: {$result['existing']}.");
    }

    public function create(): void
    {
        $season = $this->seasonOrFail();

        $this->resetForm();
        $this->form['sort_order'] = (int) $season->stages()->max('sort_order') + 1;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $stage = $this->findStage($id);

        $this->editingId = $stage->id;
        $this->form = [
            'name' => $stage->name,
            'sort_order' => $stage->sort_order,
            'mode' => $stage->mode->value,
            'group_id' => $stage->group_id ?? '',
            'qualifiers' => $stage->qualifiers ?? '',
            'has_third_place' => $stage->has_third_place,
        ];
        $this->showModal = true;
    }

    public function save(StageService $service): void
    {
        $data = $this->validate($this->rules())['form'];
        $actor = auth()->user();

        $this->editingId
            ? $service->update($this->findStage($this->editingId), $data, $actor)
            : $service->register($this->seasonOrFail(), $data, $actor);

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Fase guardada correctamente.');
    }

    public function delete(int $id, StageService $service): void
    {
        $result = $service->delete($this->findStage($id), auth()->user());

        if ($result !== true) {
            $this->dispatch('toast', type: 'error', message: $result);

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Fase eliminada.');
    }

    public function openPodium(int $id): void
    {
        $stage = $this->findStage($id)->load('results');

        $this->podiumStageId = $stage->id;
        $this->podium = [];

        foreach (DirectPodiumService::PLACES as $place) {
            $this->podium[$place] = $stage->results->where('place', $place)
                ->map(fn ($r) => ['team_id' => $r->team_id, 'season_team_player_id' => $r->season_team_player_id ?? ''])
                ->values()->all() ?: [$this->emptyPodiumRow()];
        }

        $this->resetErrorBag();
        $this->showPodium = true;
    }

    /** Otra fila en el puesto: puesto compartido. */
    public function addPodiumRow(int $place): void
    {
        if (in_array($place, DirectPodiumService::PLACES, true)) {
            $this->podium[$place][] = $this->emptyPodiumRow();
        }
    }

    public function removePodiumRow(int $place, int $index): void
    {
        unset($this->podium[$place][$index]);
        $this->podium[$place] = array_values($this->podium[$place] ?? []) ?: [$this->emptyPodiumRow()];
    }

    public function savePodium(DirectPodiumService $service): void
    {
        $stage = $this->findStage((int) $this->podiumStageId);

        $service->save($stage, array_intersect_key($this->podium, array_flip(DirectPodiumService::PLACES)), auth()->user());

        $this->showPodium = false;
        $this->podiumStageId = null;
        $this->dispatch('toast', type: 'success', message: 'Podio guardado.');
    }

    /** Abre el armado de la llave con la lista sugerida de participantes. */
    public function openBracket(int $id, StageService $stages): void
    {
        $stage = $this->findStage($id);

        $this->bracketStageId = $stage->id;
        $this->seeds = $this->suggestedSeeds($stage, $stages);
        $this->seedToAdd = '';
        $this->resetErrorBag();
        $this->showBracket = true;
    }

    /** Inscripciones de la competencia que aun no estan en la lista. */
    public function entrantOptions(): Collection
    {
        return $this->entrants()->reject(fn ($name, $id) => in_array($id, $this->seeds, true));
    }

    public function addSeed(): void
    {
        if (ctype_digit($this->seedToAdd) && $this->entrantOptions()->has((int) $this->seedToAdd)) {
            $this->seeds[] = (int) $this->seedToAdd;
        }

        $this->seedToAdd = '';
    }

    public function removeSeed(int $index): void
    {
        unset($this->seeds[$index]);
        $this->seeds = array_values($this->seeds);
    }

    /** Sube (-1) o baja (+1) a un participante en el orden. */
    public function moveSeed(int $index, int $direction): void
    {
        $target = $index + ($direction < 0 ? -1 : 1);

        if (isset($this->seeds[$index], $this->seeds[$target])) {
            [$this->seeds[$index], $this->seeds[$target]] = [$this->seeds[$target], $this->seeds[$index]];
        }
    }

    /** Sorteo: orden al azar. */
    public function shuffleSeeds(): void
    {
        shuffle($this->seeds);
    }

    public function generateBracket(BracketService $service): void
    {
        $stage = $this->findStage((int) $this->bracketStageId);

        $matches = $service->generate($stage, $this->seeds, auth()->user());

        $this->showBracket = false;
        $this->bracketStageId = null;
        $this->dispatch('toast', type: 'success', message: "Llave generada: {$matches->count()} partidos. Prográmalos en Partidos.");
    }

    public function clearBracket(int $id, BracketService $service): void
    {
        $result = $service->clear($this->findStage($id), auth()->user());

        $this->dispatch('toast', type: $result === true ? 'success' : 'error', message: $result === true ? 'Llave borrada.' : $result);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->showPodium = false;
        $this->showBracket = false;
        $this->bracketStageId = null;
        $this->podiumStageId = null;
        $this->resetForm();
    }

    public function render()
    {
        $season = $this->season();

        return view('livewire.season-stages.index', [
            'season' => $season,
            'stages' => $this->stagesOf($season),
            'entrantNames' => $this->showBracket ? $this->entrants() : collect(),
        ]);
    }

    /** Inscripciones activas de la competencia: id de season_team => nombre de la delegacion. */
    /** Fases de la competencia con su llave y su podio: las comparten render() y la exportacion a Excel. */
    private function stagesOf(?Season $season): Collection
    {
        return $season
            ? $season->stages()->with([
                'group:id,name', 'results.team:id,name', 'results.seasonTeamPlayer.player:id,first_name,last_name',
                'matches.participants' => ParticipantLabel::eagerLoad(),
            ])->get()
            : collect();
    }

    protected function authorizeExport(): void
    {
        // boot() ya exige el permiso en cada peticion; season() solo devuelve competencias que el usuario gestiona.
    }

    protected function exportTitle(): string
    {
        return 'Fases';
    }

    protected function exportRows(): iterable
    {
        return $this->stagesOf($this->season());
    }

    protected function exportColumns(): array
    {
        return [
            'Fase' => fn (SeasonStage $s) => $s->name,
            'Modo' => fn (SeasonStage $s) => $s->mode->label(),
            'Serie' => fn (SeasonStage $s) => $s->group?->name,
            'Clasifican' => fn (SeasonStage $s) => $s->qualifiers,
            'Tercer puesto' => fn (SeasonStage $s) => (bool) $s->has_third_place,
            'Partidos' => fn (SeasonStage $s) => $s->matches->count(),
            'Podio' => fn (SeasonStage $s) => $s->results->sortBy('place')
                ->map(fn ($r) => "{$r->place}.º ".($r->team?->name ?? '').($r->seasonTeamPlayer?->player
                    ? " ({$r->seasonTeamPlayer->player->last_name}, {$r->seasonTeamPlayer->player->first_name})" : ''))
                ->join('; '),
        ];
    }

    private function entrants(): Collection
    {
        $season = $this->season();

        return $season
            ? SeasonTeam::with('team:id,name')->where('season_id', $season->id)->where('is_active', true)->get()
                ->sortBy(fn (SeasonTeam $st) => $st->team->name)
                ->mapWithKeys(fn (SeasonTeam $st) => [$st->id => $st->team->name])
            : collect();
    }

    /**
     * Lista inicial de la llave: en una serie, las delegaciones de esa
     * serie con plantel en la competencia; en una fase sin serie, los
     * clasificados de las fases anteriores; si no hay ninguno, todas las
     * delegaciones de la competencia. El organizador la ajusta y ordena.
     *
     * @return list<int>
     */
    private function suggestedSeeds(SeasonStage $stage, StageService $stages): array
    {
        $season = $this->seasonOrFail();
        $all = $this->entrants()->keys();

        if ($stage->group_id) {
            return SeasonTeam::where('season_id', $season->id)->where('is_active', true)
                ->whereHas('team.editionTeams', fn ($q) => $q
                    ->where('games_edition_id', $season->games_edition_id)
                    ->where('group_id', $stage->group_id))
                ->pluck('id')->intersect($all)->values()->all();
        }

        $qualified = $season->stages()->whereKeyNot($stage->id)->where('mode', StageMode::Matches->value)->get()
            ->flatMap(fn (SeasonStage $previous) => $stages->winners($previous)->pluck('id'))
            ->intersect($all)->values();

        return ($qualified->isNotEmpty() ? $qualified : $all)->all();
    }

    private function seasonOrFail(): Season
    {
        return $this->season() ?? abort(404);
    }

    /** El id llega del navegador: se busca dentro de la competencia elegida. */
    private function findStage(int $id): SeasonStage
    {
        return SeasonStage::where('season_id', $this->seasonOrFail()->id)->findOrFail($id);
    }

    private function emptyPodiumRow(): array
    {
        return ['team_id' => '', 'season_team_player_id' => ''];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'sort_order' => '',
            'mode' => StageMode::Matches->value,
            'group_id' => '',
            'qualifiers' => '',
            'has_third_place' => false,
        ];
        $this->resetErrorBag();
    }

    /** Nombre unico y serie de la misma olimpiada: StageService. */
    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:80'],
            'form.sort_order' => ['nullable', 'integer', 'between:0,999'],
            'form.mode' => ['required', Rule::enum(StageMode::class)],
            'form.group_id' => ['nullable', 'integer'],
            'form.qualifiers' => ['nullable', 'integer', 'between:1,64'],
            'form.has_third_place' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => 'nombre',
            'form.sort_order' => 'orden',
            'form.mode' => 'modo',
            'form.group_id' => 'serie',
            'form.qualifiers' => 'clasificados',
        ];
    }
}
