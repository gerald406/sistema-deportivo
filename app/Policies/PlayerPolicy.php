<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Player;
use App\Models\User;
use Closure;

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

    /**
     * Restriccion sobre season_team_player: inscripciones cuyo delegado
     * EFECTIVO es $user. Replica en SQL la regla de
     * SeasonTeam::effectiveDelegate(): el delegado de la temporada, si
     * existe, tiene precedencia; solo si es null cuenta el delegado
     * general del club (teams.delegate_id).
     *
     * Publica y estatica para que un listado pueda precargarla con
     * withExists() (ver managedAttribute()) y evitar una consulta por
     * fila en los @can de la tabla.
     */
    public static function managedRosterConstraint(User $user): Closure
    {
        return function ($roster) use ($user) {
            $roster->whereHas('seasonTeam', function ($seasonTeam) use ($user) {
                // where(closure) agrupa el OR entre parentesis: sin esto
                // romperia la correlacion del whereHas.
                $seasonTeam->where(function ($q) use ($user) {
                    $q->where('delegate_id', $user->id)
                        ->orWhere(function ($q) use ($user) {
                            $q->whereNull('delegate_id')
                                ->whereHas('team', fn ($team) => $team->where('delegate_id', $user->id));
                        });
                });
            });
        };
    }

    /**
     * Nombre del atributo que un listado agrega con
     *   ->withExists(['seasonTeamPlayers as '.PlayerPolicy::managedAttribute($user)
     *       => PlayerPolicy::managedRosterConstraint($user)])
     * Incluye el id del usuario para no confundir un valor precargado
     * para otro usuario.
     */
    public static function managedAttribute(User $user): string
    {
        return "managed_by_user_{$user->id}";
    }

    private function belongsToDelegate(Player $player, User $user): bool
    {
        if ($player->created_by === $user->id) {
            return true;
        }

        $preloaded = self::managedAttribute($user);

        if (array_key_exists($preloaded, $player->getAttributes())) {
            return (bool) $player->getAttribute($preloaded);
        }

        return $player->seasonTeamPlayers()
            ->where(self::managedRosterConstraint($user))
            ->exists();
    }
}
