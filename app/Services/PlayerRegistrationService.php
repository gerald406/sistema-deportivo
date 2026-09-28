<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use App\Models\User;
use App\Policies\PlayerPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

class PlayerRegistrationService
{
    /**
     * @param array{dni: string, first_name: string, last_name: string, birth_date: string, is_active: bool} $data
     */
    public function register(array $data, User $actor): Player
    {
        $data['created_by'] = $actor->id;

        return Player::create($data);
    }

    public function update(Player $player, array $data): Player
    {
        $player->update($data);

        return $player;
    }

    /**
     * Sin soft deletes, un jugador con historial (season_team_player,
     * RESTRICT) no se puede borrar fisicamente. Se detecta antes de
     * intentarlo en vez de dejar que MySQL truene con un error 1451.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(Player $player): bool|string
    {
        if ($player->seasonTeamPlayers()->exists()) {
            return 'No se puede eliminar: el jugador tiene inscripciones registradas. Desactívalo en su lugar.';
        }

        try {
            $player->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: el jugador tiene historial asociado. Desactívalo en su lugar.';
        }

        // Sin esto la foto queda huerfana en storage/app/public/players.
        if ($player->photo_path) {
            Storage::disk('public')->delete($player->photo_path);
        }

        return true;
    }

    /**
     * Jugadores que $actor ve en /jugadores (union si tiene varios roles).
     * Player es un catalogo global: el alcance no es un where directo sino
     * via su roster (season_team_player -> season_team -> ...).
     *   - Admin: todos.
     *   - Organizador: con al menos una inscripcion en una temporada de un
     *     torneo suyo (tournaments.organizer_id).
     *   - Delegado: con al menos una inscripcion en un equipo cuyo delegado
     *     EFECTIVO es el (SeasonTeam::effectiveDelegate(), replicado en SQL
     *     por PlayerPolicy::managedRosterConstraint()).
     *   - Organizador o delegado: tambien los que el mismo creo
     *     (players.created_by) y aun no tienen roster; sin esto el delegado
     *     perderia de vista (y no podria corregir) un jugador recien creado
     *     antes de inscribirlo en /planteles.
     *   - Cualquier otro: ninguno.
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('admin')) {
            return $query;
        }

        $isOrganizer = $actor->hasRole('organizador');
        $isDelegate = $actor->hasRole('delegado');

        if (! $isOrganizer && ! $isDelegate) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($actor, $isOrganizer, $isDelegate) {
            $q->where('players.created_by', $actor->id);

            if ($isOrganizer) {
                $q->orWhereHas('seasonTeamPlayers.seasonTeam.season.tournament',
                    fn ($t) => $t->where('organizer_id', $actor->id));
            }

            if ($isDelegate) {
                $q->orWhereHas('seasonTeamPlayers', PlayerPolicy::managedRosterConstraint($actor));
            }
        });
    }
}
