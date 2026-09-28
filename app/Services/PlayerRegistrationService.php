<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use App\Models\User;
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
     * via su roster: es visible si tiene al menos un convocado visible en
     * /planteles (SeasonTeamPlayerService::scopeVisibleTo(): organizador ->
     * torneo suyo, delegado -> delegado EFECTIVO, union si tiene ambos).
     *   - Admin: todos.
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

        if (! $actor->hasAnyRole(['organizador', 'delegado'])) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn ($q) => $q
            ->where('players.created_by', $actor->id)
            ->orWhereHas('seasonTeamPlayers', fn ($roster) => app(SeasonTeamPlayerService::class)->scopeVisibleTo($roster, $actor)));
    }

    /**
     * El jugador esta dentro del alcance de $actor (lo usa
     * PlayerPolicy::view): misma regla que scopeVisibleTo(), aplicada a un
     * solo registro.
     */
    public function isVisibleTo(User $actor, Player $player): bool
    {
        return $this->scopeVisibleTo(Player::query(), $actor)->whereKey($player->id)->exists();
    }
}
