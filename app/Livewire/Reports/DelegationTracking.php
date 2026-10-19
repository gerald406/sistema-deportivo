<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Livewire\Concerns\ExportsExcel;
use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\Team;
use App\Services\DelegationTrackingService;
use App\Services\GamesEditionService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Seguimiento de delegaciones (solo lectura): resumen por delegacion
 * (personas, disciplinas, documentos verificados) y, al elegir una, sus
 * disciplinas con el plantel de cada una y el enlace a su ficha general
 * para cotejarla.
 *
 * editionId y teamId vienen de la URL (manipulables): edition() y team()
 * SIEMPRE los buscan dentro de lo que DelegationTrackingService deja ver.
 */
#[Layout('layouts.admin')]
#[Title('Seguimiento de delegaciones')]
class DelegationTracking extends Component
{
    use ExportsExcel;

    #[Url(as: 'olimpiada')]
    public string $editionId = '';

    /** '' = resumen; un id = detalle de esa delegacion. */
    #[Url(as: 'delegacion')]
    public string $teamId = '';

    public string $search = '';

    public string $groupFilter = '';

    /** Busqueda dentro del detalle: disciplina o participante (nombre o DNI). */
    public string $detailSearch = '';

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

        if ($this->teamId !== '' && $this->team() === null) {
            $this->teamId = '';
        }
    }

    public function updatedEditionId(): void
    {
        $this->teamId = '';
        $this->groupFilter = '';
    }

    public function updatedTeamId(): void
    {
        $this->detailSearch = '';
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

    public function team(): ?Team
    {
        $edition = $this->edition();

        if (! $edition || ! ctype_digit($this->teamId)) {
            return null;
        }

        $team = Team::find((int) $this->teamId);

        return $team && $this->service()->canView(auth()->user(), $edition, $team) ? $team : null;
    }

    public function groupOptions(): Collection
    {
        $edition = $this->edition();

        return $edition
            ? GamesEditionGroup::where('games_edition_id', $edition->id)->orderBy('sort_order')->get(['id', 'name'])
            : collect();
    }

    public function show(int $teamId): void
    {
        $this->teamId = (string) $teamId;
        $this->detailSearch = '';
    }

    public function back(): void
    {
        $this->teamId = '';
        $this->detailSearch = '';
    }

    public function render(DelegationTrackingService $service)
    {
        $edition = $this->edition();
        $team = $this->team();

        if ($edition && $team) {
            return view('livewire.reports.delegation-tracking', [
                'edition' => $edition,
                'team' => $team,
                'rows' => collect(),
                'totals' => null,
                'disciplines' => $this->filteredDetail($service->detail($edition, $team)),
                'manages' => app(GamesEditionService::class)->manages(auth()->user(), $edition),
            ]);
        }

        $rows = $edition ? $this->summaryRows() : collect();

        return view('livewire.reports.delegation-tracking', [
            'edition' => $edition,
            'team' => null,
            'rows' => $rows,
            'totals' => (object) [
                'delegations' => $rows->count(),
                'people' => $rows->sum('people'),
                'disciplines' => $rows->sum('disciplines'),
                'empty' => $rows->sum('empty'),
            ],
            'disciplines' => collect(),
            'manages' => $edition && app(GamesEditionService::class)->manages(auth()->user(), $edition),
        ]);
    }

    /** Resumen filtrado: lo comparten render() y la exportacion a Excel. */
    private function summaryRows(): Collection
    {
        $edition = $this->edition();

        return $edition
            ? $this->service()->summary(auth()->user(), $edition, trim($this->search), ctype_digit($this->groupFilter) ? (int) $this->groupFilter : null)
            : collect();
    }

    private function filteredDetail(Collection $disciplines): Collection
    {
        $term = mb_strtolower(trim($this->detailSearch));

        if ($term === '') {
            return $disciplines;
        }

        return $disciplines->filter(function ($d) use ($term) {
            if (str_contains(mb_strtolower($d->seasonTeam->season->name), $term)) {
                return true;
            }

            $d->players = $d->players->filter(fn ($e) => str_contains(mb_strtolower($e->player->last_name.' '.$e->player->first_name), $term)
                || str_contains($e->player->dni, $term))->values();

            return $d->players->isNotEmpty();
        })->values();
    }

    protected function authorizeExport(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'organizador', 'delegado']), 403);
    }

    protected function exportTitle(): string
    {
        return 'Seguimiento de delegaciones';
    }

    protected function exportRows(): iterable
    {
        return $this->summaryRows();
    }

    protected function exportColumns(): array
    {
        return [
            'Delegación' => fn ($r) => $r->entry->team->name,
            'Serie' => fn ($r) => $r->entry->group?->name,
            'Delegado titular' => fn ($r) => $r->entry->delegate?->name,
            'Personas' => fn ($r) => $r->people,
            'Varones' => fn ($r) => $r->men,
            'Damas' => fn ($r) => $r->women,
            'Disciplinas inscritas' => fn ($r) => $r->disciplines,
            'Con plantel' => fn ($r) => $r->withRoster,
            'Sin plantel' => fn ($r) => $r->empty,
            'Documentos verificados' => fn ($r) => $r->verified,
            'Pago' => fn ($r) => $r->entry->fee_paid ? 'Registrado' : 'Pendiente',
            'Estado' => fn ($r) => $r->entry->status->label(),
        ];
    }

    private function service(): DelegationTrackingService
    {
        return app(DelegationTrackingService::class);
    }
}
