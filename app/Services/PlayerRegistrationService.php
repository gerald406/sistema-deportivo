<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

class PlayerRegistrationService
{
    /**
     * Campos que llegan del formulario. created_by y documents_verified_at
     * NUNCA se toman de $data: el primero lo fija register() y el segundo
     * solo setDocumentsVerified().
     */
    private const FORM_FIELDS = [
        'dni', 'first_name', 'last_name', 'birth_date', 'gender', 'staff_type', 'employment_type', 'photo_path', 'is_active',
    ];

    /**
     * @param array{dni: string, first_name: string, last_name: string, birth_date: string, gender?: string|null, staff_type?: string|null, employment_type?: string|null, is_active: bool} $data
     */
    public function register(array $data, User $actor): Player
    {
        $player = new Player($this->formData($data));
        $player->created_by = $actor->id;
        $player->save();

        return $player;
    }

    public function update(Player $player, array $data): Player
    {
        $player->update($this->formData($data));

        return $player;
    }

    /**
     * Marca (o desmarca) que se revisaron el DNI y los documentos del
     * participante. Solo el admin o el organizador de una olimpiada en la
     * que el participante esta a su alcance; nunca el delegado.
     */
    public function setDocumentsVerified(Player $player, bool $verified, User $actor): Player
    {
        if (! $this->canVerifyDocuments($actor, $player)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador verifican documentos.');
        }

        $player->forceFill(['documents_verified_at' => $verified ? ($player->documents_verified_at ?? now()) : null])->save();

        return $player;
    }

    public function canVerifyDocuments(User $actor, Player $player): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        return $actor->hasRole('organizador')
            && $actor->can('editions.manage')
            && $this->isVisibleTo($actor, $player);
    }

    /** @return array<string, mixed> */
    private function formData(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::FORM_FIELDS));

        foreach (['gender', 'staff_type', 'employment_type'] as $optional) {
            if (array_key_exists($optional, $data) && $data[$optional] === '') {
                $data[$optional] = null;
            }
        }

        return $data;
    }

    /**
     * Sin soft deletes, un participante con historial (season_team_player,
     * RESTRICT) no se puede borrar fisicamente. Se detecta antes de
     * intentarlo en vez de dejar que MySQL truene con un error 1451.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(Player $player): bool|string
    {
        if ($player->seasonTeamPlayers()->exists()) {
            return 'No se puede eliminar: el participante tiene inscripciones registradas. Desactívalo en su lugar.';
        }

        try {
            $player->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: el participante tiene historial asociado. Desactívalo en su lugar.';
        }

        // Sin esto la foto queda huerfana en storage/app/public/players.
        if ($player->photo_path) {
            Storage::disk('public')->delete($player->photo_path);
        }

        return true;
    }

    /**
     * Participantes que $actor ve en /participantes (union si tiene varios roles).
     * Player es un catalogo global: el alcance no es un where directo sino
     * via su roster: es visible si tiene al menos un convocado visible en
     * /planteles (SeasonTeamPlayerService::scopeVisibleTo(): organizador ->
     * olimpiada suya, delegado -> delegado EFECTIVO, union si tiene ambos).
     *   - Admin: todos.
     *   - Organizador o delegado: tambien los que el mismo creo
     *     (players.created_by) y aun no tienen roster; sin esto el delegado
     *     perderia de vista (y no podria corregir) un participante recien creado
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
     * El participante esta dentro del alcance de $actor (lo usa
     * PlayerPolicy::view): misma regla que scopeVisibleTo(), aplicada a un
     * solo registro.
     */
    public function isVisibleTo(User $actor, Player $player): bool
    {
        return $this->scopeVisibleTo(Player::query(), $actor)->whereKey($player->id)->exists();
    }
}
