<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        // "Flujo de permisos" punto 3 de CLAUDE.md: el delegado NUNCA crea
        // equipos. Se revalida aqui (no solo en TeamPolicy ni ocultando el
        // boton) porque save() es invocable desde la consola del navegador.
        if (! $this->canAssignDelegate($actor)) {
            throw new AuthorizationException('Solo el organizador o el administrador pueden crear equipos.');
        }

        $data['delegate_id'] = $this->resolveDelegateId($data, $actor);
        // Deja al equipo "a cargo" de quien lo crea aunque aun no tenga
        // inscripciones (ver scopeManagedBy()).
        $data['created_by'] = $actor->id;

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
     * season_team o medallas en games_edition_medals, ambas RESTRICT)
     * no se puede borrar fisicamente. Se detecta antes de intentarlo
     * para devolver una respuesta clara al Livewire component en vez de
     * dejar que MySQL truene con un error 1451 crudo.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(Team $team): bool|string
    {
        if ($team->seasonTeams()->exists()) {
            return 'No se puede eliminar: el equipo tiene inscripciones en temporadas. Desactívalo en su lugar.';
        }

        if ($team->medals()->exists()) {
            return 'No se puede eliminar: el equipo tiene medallas registradas. Desactívalo en su lugar.';
        }

        try {
            $team->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: el equipo tiene historial asociado. Desactívalo en su lugar.';
        }

        // Sin esto el logo queda huerfano en storage/app/public/teams.
        if ($team->logo_path) {
            Storage::disk('public')->delete($team->logo_path);
        }

        return true;
    }

    /** Admin u organizador: crean equipos y eligen su delegado. */
    public function canAssignDelegate(User $actor): bool
    {
        return $actor->hasRole('admin') || $this->isOrganizer($actor);
    }

    /**
     * Equipos que $actor gestiona en /equipos.
     *   - Admin: todos.
     *   - Organizador: los "a su cargo". Team no se relaciona directo con
     *     Tournament (un equipo puede estar inscrito en temporadas de varios
     *     torneos a la vez), asi que se considera suyo el equipo que tiene
     *     al menos una inscripcion (season_team) en una temporada de un
     *     torneo suyo, o que el mismo creo (teams.created_by) y aun no
     *     inscribe.
     */
    public function scopeManagedBy(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('admin')) {
            return $query;
        }

        if ($this->isOrganizer($actor)) {
            return $query->where(fn ($q) => $q
                ->where('teams.created_by', $actor->id)
                ->orWhereHas('seasonTeams.season.tournament', fn ($t) => $t->where('organizer_id', $actor->id)));
        }

        return $query;
    }

    public function manages(User $actor, Team $team): bool
    {
        return $this->scopeManagedBy(Team::query(), $actor)->whereKey($team->id)->exists();
    }

    /**
     * Autocomplete de "Delegado responsable": busqueda server-side, nunca
     * se cargan todos los delegados. Minimo 2 caracteres, maximo 10
     * resultados.
     */
    public function searchDelegates(string $term): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return User::role('delegado')
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);
    }

    private function isOrganizer(User $actor): bool
    {
        return $actor->hasRole('organizador') && $actor->can('teams.manage');
    }

    /**
     * Un delegado jamas puede asignar (ni reasignar) el delegate_id de
     * un equipo via el formulario: siempre queda el que ya tenia. Solo
     * admin u organizador eligen delegado (o lo dejan sin asignar), y
     * solo entre usuarios con rol delegado.
     */
    private function resolveDelegateId(array $data, User $actor, ?Team $team = null): ?int
    {
        if (! $this->canAssignDelegate($actor)) {
            return $team?->delegate_id;
        }

        if (in_array($data['delegate_id'] ?? null, ['', null], true)) {
            return null;
        }

        $delegateId = (int) $data['delegate_id'];

        if (! User::role('delegado')->whereKey($delegateId)->exists()) {
            throw ValidationException::withMessages([
                'form.delegate_id' => 'El delegado debe ser un usuario con rol delegado.',
            ]);
        }

        return $delegateId;
    }
}
