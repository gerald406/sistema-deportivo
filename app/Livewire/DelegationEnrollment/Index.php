<?php

declare(strict_types=1);

namespace App\Livewire\DelegationEnrollment;

use App\Enums\Gender;
use App\Models\Category;
use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Services\DelegationEnrollmentService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Inscripcion por disciplina (D22): el delegado marca en que disciplinas
 * participa su delegacion y desde cada una va a su plantel. Quien gestiona
 * la olimpiada puede hacerlo por cualquier delegacion.
 *
 * editionId y teamId vienen de la URL: edition() y team() SIEMPRE los
 * buscan dentro de lo que el usuario puede inscribir
 * (DelegationEnrollmentService); cada accion la revalida el Service.
 */
#[Layout('layouts.admin')]
#[Title('Inscripción por disciplina')]
class Index extends Component
{
    #[Url(as: 'olimpiada')]
    public string $editionId = '';

    #[Url(as: 'delegacion')]
    public string $teamId = '';

    public string $search = '';

    /** '' | team | individual */
    public string $kindFilter = '';

    public string $categoryFilter = '';

    /** '' | M | F | X */
    public string $genderFilter = '';

    /** '' | enrolled | not_enrolled */
    public string $enrolledFilter = '';

    public function boot(): void
    {
        // El middleware de la ruta no se reaplica en /livewire/update.
        abort_unless(auth()->user()->hasAnyRole(['admin', 'organizador', 'delegado']), 403);
    }

    public function mount(): void
    {
        if ($this->edition() === null) {
            $this->editionId = (string) ($this->editionOptions()->first()?->id ?? '');
        }

        $this->pickDefaultTeam();
    }

    public function updatedEditionId(): void
    {
        $this->teamId = '';
        $this->pickDefaultTeam();
    }

    public function editionOptions(): Collection
    {
        return $this->service()->editionsFor(auth()->user());
    }

    public function edition(): ?GamesEdition
    {
        return ctype_digit($this->editionId)
            ? GamesEdition::whereKey($this->editionOptions()->pluck('id'))->find((int) $this->editionId)
            : null;
    }

    public function teamOptions(): Collection
    {
        $edition = $this->edition();

        return $edition ? $this->service()->delegationsFor(auth()->user(), $edition) : collect();
    }

    public function team(): ?Team
    {
        return ctype_digit($this->teamId) ? $this->teamOptions()->firstWhere('id', (int) $this->teamId) : null;
    }

    public function genderOptions(): array
    {
        return Gender::cases();
    }

    /** Inscribe o retira a la delegacion en la disciplina. */
    public function toggle(int $seasonId, DelegationEnrollmentService $service): void
    {
        [$season, $team] = [$this->findSeason($seasonId), $this->teamOrFail()];
        $current = SeasonTeam::where('season_id', $season->id)->where('team_id', $team->id)->where('is_active', true)->first();

        try {
            if ($current) {
                $result = $service->withdraw($current, auth()->user());
                $this->dispatch('toast', type: $result === true ? 'success' : 'error', message: $result === true ? "Se retiró de {$season->name}." : $result);

                return;
            }

            $service->enroll($season, $team, auth()->user());
            $this->dispatch('toast', type: 'success', message: "Inscrita en {$season->name}. Ahora agrega a sus participantes en el plantel.");
        } catch (ValidationException $e) {
            // Cierre de inscripciones, disciplina cerrada, delegacion retirada...: no hay campo, va al aviso.
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first());
        }
    }

    public function render(DelegationEnrollmentService $service)
    {
        $edition = $this->edition();
        $team = $this->team();
        $rows = $edition && $team ? $service->board($edition, $team) : collect();
        $term = mb_strtolower(trim($this->search));

        $visible = $rows
            ->when($term !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r->season->name), $term)))
            ->when(in_array($this->kindFilter, [DelegationEnrollmentService::TEAM, DelegationEnrollmentService::INDIVIDUAL], true),
                fn ($c) => $c->where('kind', $this->kindFilter))
            ->when(ctype_digit($this->categoryFilter), fn ($c) => $c->filter(fn ($r) => $r->season->category_id === (int) $this->categoryFilter))
            ->when(Gender::tryFrom($this->genderFilter), fn ($c, Gender $g) => $c->filter(fn ($r) => $r->season->gender === $g))
            ->when($this->enrolledFilter === 'enrolled', fn ($c) => $c->filter(fn ($r) => $r->seasonTeam?->is_active))
            ->when($this->enrolledFilter === 'not_enrolled', fn ($c) => $c->reject(fn ($r) => $r->seasonTeam?->is_active))
            ->values();

        return view('livewire.delegation-enrollment.index', [
            'edition' => $edition,
            'team' => $team,
            'rows' => $visible,
            'summary' => (object) [
                'enrolled' => $rows->filter(fn ($r) => $r->seasonTeam?->is_active)->count(),
                'participants' => $rows->filter(fn ($r) => $r->seasonTeam?->is_active)->sum('participants'),
                'total' => $rows->count(),
            ],
            'canEnroll' => $edition && $team && $rows->isNotEmpty()
                && $service->canEnroll(auth()->user(), $rows->first()->season, $team),
            'categories' => $edition
                ? Category::whereIn('id', $rows->pluck('season.category_id')->filter()->unique())->orderBy('min_age')->get(['id', 'name'])
                : collect(),
        ]);
    }

    private function service(): DelegationEnrollmentService
    {
        return app(DelegationEnrollmentService::class);
    }

    private function pickDefaultTeam(): void
    {
        if ($this->team() === null) {
            $this->teamId = (string) ($this->teamOptions()->first()?->id ?? '');
        }
    }

    private function teamOrFail(): Team
    {
        return $this->team() ?? abort(404);
    }

    /** El id llega del navegador: se busca dentro de la olimpiada elegida. */
    private function findSeason(int $id): Season
    {
        return Season::where('games_edition_id', $this->edition()?->id ?? 0)->findOrFail($id);
    }
}
