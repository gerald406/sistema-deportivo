<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Player;
use App\Models\User;

class PlayerPolicy
{
    /**
     * Player es global (no pertenece a un solo Team), asi que la
     * pertenencia real se verifica a traves del roster vigente:
     * Player -> SeasonTeamPlayer -> SeasonTeam -> Team.delegate_id.
     * Esto evita el IDOR de "cualquier delegado edita cualquier
     * jugador" y respeta que un jugador puede estar en el roster de
     * varios equipos historicamente.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    public function view(User $user, Player $player): bool
    {
        if ($user->hasAnyRole(['admin', 'organizador'])) {
            return true;
        }

        return $this->belongsToDelegate($player, $user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('delegado') && $user->can('players.manage');
    }

    public function update(User $user, Player $player): bool
    {
        return $user->hasRole('delegado')
            && $user->can('players.manage')
            && $this->belongsToDelegate($player, $user);
    }

    public function delete(User $user, Player $player): bool
    {
        return $user->hasRole('delegado')
            && $user->can('players.manage')
            && $this->belongsToDelegate($player, $user);
    }

    private function belongsToDelegate(Player $player, User $user): bool
    {
        return $player->seasonTeamPlayers()
            ->whereHas('seasonTeam.team', function ($query) use ($user) {
                $query->where('delegate_id', $user->id);
            })
            ->exists();
    }
}
