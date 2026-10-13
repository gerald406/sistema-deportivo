<?php

declare(strict_types=1);

namespace App\Livewire\SeasonStages;

use App\Enums\EditionTeamStatus;
use App\Enums\StageMode;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\SeasonTeamPlayer;
use App\Models\Team;
use App\Services\DirectPodiumService;
use App\Services\StageService;
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

    /** Delegaciones inscritas (y habilitadas) en la olimpiada: las que pueden subir al podio. */
    public function teamOptions(): Collection
    {
        $season = $this->season();

        return $season
            ? Team::whereHas('editionTeams', fn ($q) => $q
                ->where('games_edition_id', $season->games_edition_id)
                ->where('status', EditionTeamStatus::Registered->value))
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

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->showPodium = false;
        $this->podiumStageId = null;
        $this->resetForm();
    }

    public function render()
    {
        $season = $this->season();

        return view('livewire.season-stages.index', [
            'season' => $season,
            'stages' => $season
                ? $season->stages()->with(['group:id,name', 'results.team:id,name', 'results.seasonTeamPlayer.player:id,first_name,last_name'])->get()
                : collect(),
        ]);
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
