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
     * @param array{name: string, is_active: bool, logo_path?: string} $data
     */
    public function register(array $data, User $actor): Team
    {
        // "Flujo de permisos" punto 3 de CLAUDE.md: el delegado NUNCA crea
        // equipos. Se revalida aqui (no solo en TeamPolicy ni ocultando el
        // boton) porque save() es invocable desde la consola del navegador.
        if (! $this->canCreate($actor)) {
            throw new AuthorizationException('Solo el organizador o el administrador pueden crear delegaciones.');
        }

        // Deja al equipo "a cargo" de quien lo crea aunque aun no tenga
        // inscripciones (ver scopeManagedBy()).
        $data['created_by'] = $actor->id;

        return Team::create($data);
    }

    public function update(Team $team, array $data, User $actor): Team
    {
        // Revalidado aqui: el delegado ya no edita su delegacion (D4).
        if (! $actor->hasRole('admin') && ! $this->manages($actor, $team)) {
            throw new AuthorizationException('Solo el organizador a cargo o el administrador pueden editar la delegación.');
        }

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
        if ($team->editionTeams()->exists()) {
            return 'No se puede eliminar: la delegación está inscrita en una olimpiada. Desactívala en su lugar.';
        }

        if ($team->seasonTeams()->exists()) {
            return 'No se puede eliminar: el equipo tiene inscripciones en competencias. Desactívalo en su lugar.';
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

    /** Admin u organizador: crean delegaciones. */
    public function canCreate(User $actor): bool
    {
        return $actor->hasRole('admin') || $this->isOrganizer($actor);
    }

    /**
     * Equipos que $actor ve en /equipos (union si tiene varios roles).
     *   - Admin: todos.
     *   - Organizador: los "a su cargo" (ver whereOrganizerScope()).
     *   - Delegado: aquellas de las que es titular o suplente en alguna
     *     olimpiada (EditionTeamService::scopeDelegatedTo()).
     *   - Cualquier otro: ninguno.
     */
    public function scopeManagedBy(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('admin')) {
            return $query;
        }

        $isOrganizer = $this->isOrganizer($actor);
        $isDelegate = $actor->hasRole('delegado');

        if (! $isOrganizer && ! $isDelegate) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($actor, $isOrganizer, $isDelegate) {
            if ($isOrganizer) {
                $q->orWhere(fn ($o) => $this->whereOrganizerScope($o, $actor));
            }

            if ($isDelegate) {
                $q->orWhere(fn ($d) => app(EditionTeamService::class)->scopeDelegatedTo($d, $actor));
            }
        });
    }

    /**
     * El equipo esta a cargo del organizador (lo usa TeamPolicy para
     * update/delete: ser delegado de un equipo NO da permisos de
     * organizador sobre el, aunque el usuario tenga ambos roles).
     */
    public function manages(User $actor, Team $team): bool
    {
        return $this->isOrganizer($actor)
            && $this->whereOrganizerScope(Team::query(), $actor)->whereKey($team->id)->exists();
    }

    /**
     * Team no se relaciona directo con GamesEdition (un equipo puede estar
     * inscrito en competencias de varias olimpiadas a la vez): es del organizador
     * si esta inscrito en una olimpiada suya (games_edition_teams), si tiene
     * al menos una inscripcion (season_team) en una competencia de una
     * olimpiada suya, o si el mismo lo creo (teams.created_by) y aun no lo
     * inscribe.
     */
    private function whereOrganizerScope(Builder $query, User $actor): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('teams.created_by', $actor->id)
            ->orWhereHas('editionTeams.edition', fn ($t) => $t->where('organizer_id', $actor->id))
            ->orWhereHas('seasonTeams.season.gamesEdition', fn ($t) => $t->where('organizer_id', $actor->id)));
    }

    private function isOrganizer(User $actor): bool
    {
        return $actor->hasRole('organizador') && $actor->can('teams.manage');
    }
}
