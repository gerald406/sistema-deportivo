<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use App\Models\User;
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
}
