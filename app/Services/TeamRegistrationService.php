<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Toda la logica de negocio de Team vive aqui, no en el componente
 * Livewire ni en el modelo: el componente solo autoriza (Policy) y
 * delega. Esto es lo que pide la arquitectura del proyecto ("logica de
 * negocio solo en app/Services").
 */
class TeamRegistrationService
{
    /**
     * @param array{name: string, delegate_id: int|null, is_active: bool} $data
     */
    public function register(array $data, User $actor): Team
    {
        $data['delegate_id'] = $this->resolveDelegateId($data, $actor);

        return Team::create($data);
    }

    public function update(Team $team, array $data, User $actor): Team
    {
        $data['delegate_id'] = $this->resolveDelegateId($data, $actor, $team);

        $team->update($data);

        return $team;
    }

    /**
     * Sin soft deletes, un equipo con historial (inscripciones en
     * season_team, que usa RESTRICT) no se puede borrar fisicamente.
     * Se detecta antes de intentarlo para devolver una respuesta clara
     * al Livewire component en vez de dejar que MySQL truene con un
     * error 1451 crudo.
     */
    public function delete(Team $team): bool
    {
        if ($team->seasonTeams()->exists()) {
            return false;
        }

        try {
            $team->delete();
        } catch (QueryException) {
            return false;
        }

        return true;
    }

    /**
     * Un delegado jamas puede asignar (ni reasignar) el delegate_id de
     * un equipo a otro usuario via el formulario: siempre queda como el
     * mismo. Solo admin puede elegir un delegado distinto o dejarlo sin
     * asignar.
     */
    private function resolveDelegateId(array $data, User $actor, ?Team $team = null): ?int
    {
        if ($actor->hasRole('admin')) {
            return $data['delegate_id'] !== '' ? (int) $data['delegate_id'] : null;
        }

        return $team?->delegate_id ?? $actor->id;
    }
}
