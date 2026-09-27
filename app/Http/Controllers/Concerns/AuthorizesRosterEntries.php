<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\SeasonTeamPlayer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Lote de ids de season_team_player que llega del navegador (carnets y
 * fichas de inscripcion): se sanea, se carga y se autoriza CADA ficha con
 * SeasonTeamPlayerPolicy::printCard. Compartido por PlayerCardController y
 * PlayerRegistrationController en vez de repetir un authorizePlayer()
 * privado en cada uno, como hacia el proyecto de referencia.
 */
trait AuthorizesRosterEntries
{
    /** Tope por PDF: DomPDF con cientos de imagenes base64 agota memoria. */
    protected int $maxEntriesPerPdf = 150;

    /**
     * @param  mixed  $rawIds  "1,2,3" o array de ids
     * @param  array<int, string>  $with  relaciones a cargar
     * @return Collection<int, SeasonTeamPlayer> ordenadas por equipo y dorsal
     */
    protected function authorizedEntries(mixed $rawIds, array $with): Collection
    {
        $ids = collect(is_array($rawIds) ? $rawIds : explode(',', (string) $rawIds))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        abort_if($ids->isEmpty(), 422, 'Selecciona al menos un jugador.');
        abort_if($ids->count() > $this->maxEntriesPerPdf, 422, "Máximo {$this->maxEntriesPerPdf} jugadores por PDF.");

        $entries = SeasonTeamPlayer::with($with)
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (SeasonTeamPlayer $e) => [$e->seasonTeam->team->name, $e->shirt_number ?? 999])
            ->values();

        abort_if($entries->count() !== $ids->count(), 404, 'Alguno de los jugadores no existe.');

        // Si una sola no corresponde, 403 para todo el lote (sin revelar las demas).
        $entries->each(fn (SeasonTeamPlayer $e) => Gate::authorize('printCard', $e));

        return $entries;
    }
}
