<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GamesEdition;
use App\Models\GamesEditionGroup;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Series de una olimpiada (Art. 8 de las bases: "cuatro series"). El
 * numero y el nombre de las series son datos: el organizador crea las que
 * necesite. Solo las gestiona quien gestiona la olimpiada
 * (GamesEditionService::manages()).
 */
class EditionGroupService
{
    public function __construct(private GamesEditionService $editions)
    {
    }

    /**
     * @param array{name: string, sort_order: int|string|null} $data
     */
    public function register(GamesEdition $edition, array $data, User $actor): GamesEditionGroup
    {
        $this->ensureManages($actor, $edition);
        $this->ensureUniqueName($edition, $data['name']);

        return $edition->groups()->create([
            'name' => trim($data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    /**
     * @param array{name: string, sort_order: int|string|null} $data
     */
    public function update(GamesEditionGroup $group, array $data, User $actor): GamesEditionGroup
    {
        $this->ensureManages($actor, $group->edition);
        $this->ensureUniqueName($group->edition, $data['name'], $group->id);

        $group->update([
            'name' => trim($data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return $group;
    }

    /**
     * games_edition_teams.group_id es RESTRICT: una serie con delegaciones
     * no se borra. Devuelve true o el mensaje exacto para el toast.
     */
    public function delete(GamesEditionGroup $group, User $actor): bool|string
    {
        $this->ensureManages($actor, $group->edition);

        if ($group->editionTeams()->exists()) {
            return 'No se puede eliminar: la serie tiene delegaciones. Muévelas a otra serie primero.';
        }

        try {
            $group->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la serie tiene historial asociado.';
        }

        return true;
    }

    private function ensureManages(User $actor, GamesEdition $edition): void
    {
        if (! $this->editions->manages($actor, $edition)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador gestionan sus series.');
        }
    }

    private function ensureUniqueName(GamesEdition $edition, string $name, ?int $ignoreId = null): void
    {
        $exists = $edition->groups()
            ->where('name', trim($name))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['form.name' => 'Ya existe una serie con ese nombre en esta olimpiada.']);
        }
    }
}
