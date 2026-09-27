<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Venue;
use Illuminate\Database\QueryException;

class VenueService
{
    /**
     * @param array{name: string, address: string|null, latitude: float|null, longitude: float|null, is_active: bool} $data
     */
    public function register(array $data): Venue
    {
        return Venue::create($data);
    }

    public function update(Venue $venue, array $data): Venue
    {
        $venue->update($data);

        return $venue;
    }

    /**
     * matches.venue_id y games_editions.host_venue_id son SET NULL en la
     * BD: borrar una sede en uso dejaria partidos/ediciones sin sede en
     * silencio. Se bloquea aqui (mismo criterio que deleteDiscipline).
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(Venue $venue): bool|string
    {
        if ($venue->matches()->exists()) {
            return 'No se puede eliminar: la sede tiene partidos programados. Desactívala en su lugar.';
        }

        if ($venue->gamesEditionsHosted()->exists()) {
            return 'No se puede eliminar: la sede es anfitriona de una edición. Desactívala en su lugar.';
        }

        try {
            $venue->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la sede tiene historial asociado. Desactívala en su lugar.';
        }

        return true;
    }
}
