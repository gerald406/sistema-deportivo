<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Player;
use App\Models\User;

class PlayerPolicy
{
    /**
     * Player es global (no pertenece a un solo Team). La pertenencia se
     * verifica de dos formas, cualquiera basta:
     *   1) Roster vigente: Player -> SeasonTeamPlayer -> SeasonTeam -> Team.delegate_id.
     *   2) created_by: el delegado que registro el jugador en el catalogo,
     *      necesario porque un jugador recien creado puede no tener
     *      todavia ninguna inscripcion (la inscripcion es Fase 5).
     * Esto evita el IDOR de "cualquier delegado edita cualquier jugador"
     * y respeta que un jugador puede estar en el roster de varios
     * equipos historicamente.
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
        if ($player->created_by === $user->id) {
            return true;
        }

        return $player->seasonTeamPlayers()
            ->whereHas('seasonTeam.team', function ($query) use ($user) {
                $query->where('delegate_id', $user->id);
            })
            ->exists();
    }
}
