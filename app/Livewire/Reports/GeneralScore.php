<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\GamesEdition;
use App\Models\Team;
use App\Services\AwardService;
use App\Services\GamesEditionService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Puntaje general de una olimpiada: clasificacion de las delegaciones por
 * los puntos de sus podios. Solo lectura; el calculo lo mantiene
 * AwardService. "Recalcular" es una red de seguridad para quien gestiona
 * la olimpiada.
 *
 * editionId viene de la URL (?olimpiada=): edition() siempre lo busca
 * dentro de las olimpiadas que el usuario puede ver.
 */
#[Layout('layouts.admin')]
#[Title('Puntaje general')]
class GeneralScore extends Component
{
    #[Url(as: 'olimpiada')]
    public string $editionId = '';

    /** Delegacion cuyo detalle de premios esta abierto ('' = ninguna). */
    public string $openTeamId = '';

    /** Reportes: permiso reports.view (los 3 roles). */
    public function boot(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function mount(): void
    {
        if (! $this->edition()) {
            $this->editionId = (string) ($this->editionOptions()->first()?->id ?? '');
        }
    }

    public function updatedEditionId(): void
    {
        $this->openTeamId = '';
    }

    /** Olimpiadas visibles para el usuario: activas primero. */
    public function editionOptions(): Collection
    {
        return app(GamesEditionService::class)->scopeVisibleTo(GamesEdition::query(), auth()->user())
            ->orderByDesc('is_active')->orderByDesc('start_date')->orderByDesc('id')
            ->get(['id', 'name', 'is_active', 'organizer_id']);
    }

    public function edition(): ?GamesEdition
    {
        return ctype_digit($this->editionId)
            ? app(GamesEditionService::class)->scopeVisibleTo(GamesEdition::query(), auth()->user())->find((int) $this->editionId)
            : null;
    }

    public function canRecalculate(): bool
    {
        $edition = $this->edition();

        return $edition !== null && app(GamesEditionService::class)->manages(auth()->user(), $edition);
    }

    public function toggleTeam(int $teamId): void
    {
        $this->openTeamId = $this->openTeamId === (string) $teamId ? '' : (string) $teamId;
    }

    /** Reconstruye el puntaje desde los podios. Solo quien gestiona la olimpiada. */
    public function recalculate(AwardService $awards): void
    {
        $edition = $this->edition() ?? abort(404);
        abort_unless(app(GamesEditionService::class)->manages(auth()->user(), $edition), 403);

        $awards->rebuild($edition);

        $this->dispatch('toast', type: 'success', message: 'Puntaje general recalculado.');
    }

    public function render(AwardService $awards)
    {
        $edition = $this->edition();
        $table = $edition ? $awards->table($edition) : collect();

        // El detalle solo de una delegacion que figura en la tabla.
        $openTeam = $table->firstWhere('team.id', (int) $this->openTeamId)?->team;

        return view('livewire.reports.general-score', [
            'edition' => $edition,
            'table' => $table,
            'openTeam' => $openTeam,
            'detail' => $edition && $openTeam instanceof Team ? $awards->detail($edition, $openTeam) : collect(),
        ]);
    }
}
