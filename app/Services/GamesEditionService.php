<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GamesEdition;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * La olimpiada (GamesEdition) es el eje del sistema: tiene un organizador
 * responsable y de ella cuelgan las competencias (Season). El componente
 * Livewire solo autoriza (GamesEditionPolicy) y delega aqui.
 *
 * Reglas confirmadas (docs/fases/00-especificacion.md, D1 y D2):
 *   - Solo el admin crea y borra olimpiadas y elige su organizador.
 *   - El organizador asignado edita los datos de SU olimpiada, pero no
 *     cambia organizer_id.
 */
class GamesEditionService
{
    /**
     * @param array{name: string, organizer_id: int|string|null, start_date: string|null, end_date: string|null, host_venue_id: int|null, is_active: bool, logo?: string} $data
     */
    public function register(array $data, User $actor): GamesEdition
    {
        // Se revalida aqui y no solo en GamesEditionPolicy::create: cualquier
        // metodo publico del componente es invocable desde el navegador.
        if (! $actor->hasRole('admin')) {
            throw new AuthorizationException('Solo el administrador puede crear olimpiadas.');
        }

        $data['organizer_id'] = $this->resolveOrganizerId($data, $actor);

        return GamesEdition::create($data);
    }

    public function update(GamesEdition $edition, array $data, User $actor): GamesEdition
    {
        if (! $this->manages($actor, $edition)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador pueden editarla.');
        }

        $data['organizer_id'] = $this->resolveOrganizerId($data, $actor, $edition);
        $edition->update($data);

        return $edition;
    }

    /**
     * - seasons.games_edition_id es RESTRICT: una olimpiada con
     *   competencias no se borra.
     * - games_edition_medals es CASCADE: se perderia el medallero.
     * Ambos casos se bloquean aqui antes de que MySQL truene con un 1451.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(GamesEdition $edition): bool|string
    {
        if ($edition->seasons()->exists()) {
            return 'No se puede eliminar: la olimpiada tiene competencias registradas. Desactívala en su lugar.';
        }

        if ($edition->medals()->exists()) {
            return 'No se puede eliminar: la olimpiada tiene medallero registrado. Desactívala en su lugar.';
        }

        try {
            $edition->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la olimpiada tiene historial asociado. Desactívala en su lugar.';
        }

        // Sin esto el logo queda huerfano en storage/app/public/editions.
        if ($edition->logo) {
            Storage::disk('public')->delete($edition->logo);
        }

        return true;
    }

    /**
     * Usuarios que pueden ser organizadores de una olimpiada. organizer_id
     * es RESTRICT y el organizador es quien la gestiona (Policy), asi que
     * solo tiene sentido un organizador o un admin.
     */
    public function organizerCandidates(): Collection
    {
        return User::role(['organizador', 'admin'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Regla unica de "quien gestiona la olimpiada": el admin cualquiera;
     * el organizador solo la que le asignaron. La reutilizan
     * GamesEditionPolicy y todo lo que cuelga de la olimpiada.
     */
    public function manages(User $actor, GamesEdition $edition): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        return $actor->hasRole('organizador')
            && $actor->can('editions.manage')
            && $edition->organizer_id === $actor->id;
    }

    /** Olimpiadas que $actor gestiona: misma regla que manages(). */
    public function scopeManagedBy(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('admin')) {
            return $query;
        }

        if (! ($actor->hasRole('organizador') && $actor->can('editions.manage'))) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('organizer_id', $actor->id);
    }

    /**
     * Olimpiadas que $actor ve en el medallero de /reportes: las que tienen
     * alguna competencia visible para el (SeasonService::scopeVisibleTo()).
     * El admin ve todas, incluso las que aun no tienen competencias.
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('admin')) {
            return $query;
        }

        return $query->whereHas('seasons', fn ($s) => app(SeasonService::class)->scopeVisibleTo($s, $actor));
    }

    /**
     * Un organizador jamas puede asignar (ni transferir) una olimpiada a
     * otro usuario via el formulario: siempre conserva al organizador
     * actual. Solo el admin elige, y solo entre organizerCandidates().
     */
    private function resolveOrganizerId(array $data, User $actor, ?GamesEdition $edition = null): int
    {
        if (! $actor->hasRole('admin')) {
            return $edition->organizer_id;
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
