<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Logica de negocio de Tournament. El componente Livewire solo autoriza
 * (TournamentPolicy) y delega aqui.
 */
class TournamentService
{
    /**
     * @param array{name: string, organizer_id: int|string|null, is_active: bool, logo?: string} $data
     */
    public function register(array $data, User $actor): Tournament
    {
        $data['organizer_id'] = $this->resolveOrganizerId($data, $actor);

        return Tournament::create($data);
    }

    public function update(Tournament $tournament, array $data, User $actor): Tournament
    {
        $data['organizer_id'] = $this->resolveOrganizerId($data, $actor, $tournament);

        $tournament->update($data);

        return $tournament;
    }

    /**
     * seasons usa RESTRICT sobre tournament_id (sin soft deletes): un
     * torneo con temporadas no se puede borrar. Se detecta antes de
     * intentarlo en vez de dejar que MySQL truene con un 1451.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(Tournament $tournament): bool|string
    {
        if ($tournament->seasons()->exists()) {
            return 'No se puede eliminar: el torneo tiene temporadas registradas. Desactívalo en su lugar.';
        }

        try {
            $tournament->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: el torneo tiene historial asociado. Desactívalo en su lugar.';
        }

        // Sin esto el logo queda huerfano en storage/app/public/tournaments.
        if ($tournament->logo) {
            Storage::disk('public')->delete($tournament->logo);
        }

        return true;
    }

    /**
     * Usuarios que pueden ser organizadores de un torneo. organizer_id es
     * RESTRICT y el organizador es quien gestiona el torneo (Policy), asi
     * que solo tiene sentido un organizador o un admin.
     */
    public function organizerCandidates()
    {
        return User::role(['organizador', 'admin'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Mismo criterio que TeamRegistrationService::resolveDelegateId(): un
     * organizador jamas puede asignar (ni transferir) un torneo a otro
     * usuario via el formulario, siempre queda como dueno el mismo. Solo
     * el admin elige organizador, y solo entre organizerCandidates().
     */
    private function resolveOrganizerId(array $data, User $actor, ?Tournament $tournament = null): int
    {
        if (! $actor->hasRole('admin')) {
            return $tournament?->organizer_id ?? $actor->id;
        }

        $organizerId = (int) ($data['organizer_id'] ?? 0);

        if (! $this->organizerCandidates()->contains('id', $organizerId)) {
            throw ValidationException::withMessages([
                'form.organizer_id' => 'El organizador debe ser un usuario activo con rol organizador o admin.',
            ]);
        }

        return $organizerId;
    }
}
