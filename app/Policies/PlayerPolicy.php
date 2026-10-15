<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Player;
use App\Models\User;
use App\Services\PlayerRegistrationService;
use Closure;

class PlayerPolicy
{
    /**
     * Player es global (no pertenece a un solo Team). La pertenencia se
     * verifica de dos formas, cualquiera basta:
     *   1) Roster vigente: Player -> SeasonTeamPlayer -> SeasonTeam -> delegados de la
     *      delegacion en la olimpiada (SeasonTeamPolicy::delegateConstraint()).
     *   2) created_by: el delegado que registro el participante en el catalogo,
     *      necesario porque un participante recien creado puede no tener
     *      todavia ninguna inscripcion (la inscripcion es Fase 5).
     * Esto evita el IDOR de "cualquier delegado edita cualquier participante"
     * y respeta que un participante puede estar en el roster de varios
     * equipos historicamente.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'organizador', 'delegado']);
    }

    /**
     * Mismo alcance que el listado de /participantes (una sola fuente de
     * verdad): PlayerRegistrationService::scopeVisibleTo(). El admin pasa
     * por Gate::before.
     */
    public function view(User $user, Player $player): bool
    {
        return app(PlayerRegistrationService::class)->isVisibleTo($user, $player);
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
     * Marcar los documentos como verificados: el organizador de la
     * olimpiada (admin por Gate::before); nunca el delegado. Misma regla
     * que revalida PlayerRegistrationService::setDocumentsVerified().
     */
    public function verifyDocuments(User $user, Player $player): bool
    {
        return app(PlayerRegistrationService::class)->canVerifyDocuments($user, $player);
    }

    /**
     * Subir, reemplazar o quitar la resolucion (PDF). Misma regla que
     * revalida PlayerRegistrationService::attachResolution(): el delegado
     * solo mientras los documentos no esten verificados.
     */
    public function manageResolution(User $user, Player $player): bool
    {
        return app(PlayerRegistrationService::class)->canManageResolution($user, $player);
    }

    /**
     * Restriccion sobre season_team_player: planteles de una delegacion de
     * la que $user es delegado titular o suplente
     * (SeasonTeamPolicy::delegateConstraint(), la unica version SQL de
     * SeasonTeam::hasDelegate()).
     *
     * Publica y estatica para que un listado pueda precargarla con
     * withExists() (ver managedAttribute()) y evitar una consulta por
     * fila en los @can de la tabla.
     */
    public static function managedRosterConstraint(User $user): Closure
    {
        return fn ($roster) => $roster->whereHas('seasonTeam', SeasonTeamPolicy::delegateConstraint($user));
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
