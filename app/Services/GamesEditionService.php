<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GamesEdition;
use Illuminate\Database\QueryException;

/**
 * Capa opcional "Olimpiadas": una GamesEdition agrupa temporadas de
 * varios deportes y acumula un medallero por equipo. Modulo exclusivo del
 * admin (el componente lo verifica en boot()).
 */
class GamesEditionService
{
    /**
     * @param array{name: string, start_date: string|null, end_date: string|null, host_venue_id: int|null, is_active: bool} $data
     */
    public function register(array $data): GamesEdition
    {
        return GamesEdition::create($data);
    }

    public function update(GamesEdition $edition, array $data): GamesEdition
    {
        $edition->update($data);

        return $edition;
    }

    /**
     * - seasons.games_edition_id es SET NULL: borrar la edicion
     *   desvincularia sus temporadas en silencio.
     * - games_edition_medals es CASCADE: se perderia el medallero.
     * Ambos casos se bloquean aqui.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(GamesEdition $edition): bool|string
    {
        if ($edition->seasons()->exists()) {
            return 'No se puede eliminar: la edición tiene temporadas vinculadas. Desactívala en su lugar.';
        }

        if ($edition->medals()->exists()) {
            return 'No se puede eliminar: la edición tiene medallero registrado. Desactívala en su lugar.';
        }

        try {
            $edition->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la edición tiene historial asociado. Desactívala en su lugar.';
        }

        return true;
    }
}
