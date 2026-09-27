<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use App\Models\User;
use Illuminate\Database\QueryException;

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
     */
    public function delete(Player $player): bool
    {
        if ($player->seasonTeamPlayers()->exists()) {
            return false;
        }

        try {
            $player->delete();
        } catch (QueryException) {
            return false;
        }

        return true;
    }
}
