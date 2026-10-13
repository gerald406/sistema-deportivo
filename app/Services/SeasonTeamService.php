<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EditionTeamStatus;
use App\Enums\SeasonStatus;
use App\Models\EventParticipant;
use App\Models\GamesEditionTeam;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\User;
use App\Policies\SeasonTeamPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Inscripcion de equipos a competencias (season_team). Reglas confirmadas
 * ("Flujo de permisos" punto 4 de CLAUDE.md):
 *   - Organizador (de esa olimpiada) y admin: inscriben cualquier equipo, en
 *     Borrador o En curso, y eligen delegado de competencia.
 *   - CAMBIO DE REGLA: el delegado ya NO inscribe (antes lo hacia con sus
 *     equipos en Borrador). No crea ni edita season_team en ningun estado.
 *   - Nadie inscribe en una competencia Cerrada.
 */
class SeasonTeamService
{
    /**
     * @param array{season_id: int, team_id: int, category_id: int|null, is_active: bool} $data
     */
    public function register(array $data, User $actor): SeasonTeam
    {
        $season = Season::with('gamesEdition')->findOrFail($data['season_id']);
        $team = Team::findOrFail($data['team_id']);

        // Revalidado aqui (no solo en SeasonTeamPolicy): un delegado que
        // llame a save() desde la consola del navegador no inscribe nada.
        if (! $this->isManager($actor, $season)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador pueden inscribir equipos.');
        }

        if (! $this->seasonsFor($actor)->contains('id', $season->id)) {
            throw ValidationException::withMessages([
                'form.season_id' => $this->seasonRejectionReason($season),
            ]);
        }

        if (! $team->is_active) {
            throw ValidationException::withMessages(['form.team_id' => 'El equipo está inactivo.']);
        }

        if (SeasonTeam::where('season_id', $season->id)->where('team_id', $team->id)->exists()) {
            throw ValidationException::withMessages(['form.team_id' => 'El equipo ya está inscrito en esta competencia.']);
        }

        return SeasonTeam::create([
            'season_id' => $season->id,
            'team_id' => $team->id,
            'category_id' => $data['category_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Inscripcion de la delegacion en la competencia, creada si no existe
     * (S3: nace sola al registrar el primer participante). Exige que la
     * delegacion este inscrita en la olimpiada y no retirada ni
     * descalificada. No autoriza: quien llama ya decidio que el actor
     * puede (SeasonTeamPlayerService::registerInCompetition()).
     */
    public function ensureFor(Season $season, Team $team): SeasonTeam
    {
        $entry = GamesEditionTeam::where('games_edition_id', $season->games_edition_id)
            ->where('team_id', $team->id)
            ->first();

        if ($entry === null) {
            throw ValidationException::withMessages(['form.team_id' => 'La delegación no está inscrita en la olimpiada.']);
        }

        if ($entry->status !== EditionTeamStatus::Registered) {
            throw ValidationException::withMessages(['form.team_id' => 'La delegación está retirada o descalificada: no puede inscribir participantes.']);
        }

        return SeasonTeam::firstOrCreate(
            ['season_id' => $season->id, 'team_id' => $team->id],
            ['category_id' => $season->category_id, 'is_active' => true],
        );
    }

    /**
     * Competencia y equipo NO se cambian: una inscripcion a otra competencia
     * es otra inscripcion (retirar + inscribir). Solo categoria y estado.
     *
     * @param array{category_id: int|null, is_active: bool} $data
     */
    public function update(SeasonTeam $seasonTeam, array $data, User $actor): SeasonTeam
    {
        if (! $this->isManager($actor, $seasonTeam->season)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador pueden editar inscripciones.');
        }

        $seasonTeam->update([
            'category_id' => $data['category_id'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        return $seasonTeam;
    }

    /**
     * - event_participants es polimorfica SIN FK: si el equipo ya fue
     *   programado en algun partido, borrar dejaria participaciones
     *   huerfanas. Se bloquea explicitamente.
     * - El roster (season_team_player) se borra en CASCADE, pero si algun
     *   participante tiene goles/sanciones/relevos, esas FKs son RESTRICT y
     *   MySQL lo rechaza: se captura y se devuelve un mensaje claro.
     * - season_standings se va en CASCADE (es un dato calculado).
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(SeasonTeam $seasonTeam): bool|string
    {
        if ($seasonTeam->eventParticipants()->exists()) {
            return 'No se puede retirar: el equipo ya está programado en partidos de la competencia. Desactiva la inscripción en su lugar.';
        }

        $rosterIds = $seasonTeam->roster()->pluck('id');

        if ($rosterIds->isNotEmpty()
            && EventParticipant::where('participant_type', 'season_team_player')->whereIn('participant_id', $rosterIds)->exists()) {
            return 'No se puede retirar: participantes del equipo ya compitieron en la competencia. Desactiva la inscripción en su lugar.';
        }

        try {
            $seasonTeam->delete();
        } catch (QueryException) {
            return 'No se puede retirar: el plantel del equipo tiene historial (goles, sanciones o relevos). Desactiva la inscripción en su lugar.';
        }

        return true;
    }

    /**
     * Inscripciones que $actor ve en /inscripciones (union si tiene varios
     * roles). Tambien es la base del alcance de /planteles y /participantes
     * (SeasonTeamPlayerService::scopeVisibleTo()).
     *   - Admin: todas.
     *   - Organizador: las de competencias de una olimpiada suya.
     *   - Delegado: las de equipos cuyo delegado EFECTIVO es el
     *     (SeasonTeamPolicy::delegateConstraint()).
     *   - Cualquier otro: ninguna.
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('admin')) {
            return $query;
        }

        $isOrganizer = $actor->hasRole('organizador');
        $isDelegate = $actor->hasRole('delegado');

        if (! $isOrganizer && ! $isDelegate) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($actor, $isOrganizer, $isDelegate) {
            if ($isOrganizer) {
                $q->orWhereHas('season.gamesEdition', fn ($t) => $t->where('organizer_id', $actor->id));
            }

            if ($isDelegate) {
                $q->orWhere(SeasonTeamPolicy::delegateConstraint($actor));
            }
        });
    }

    /**
     * La inscripcion esta dentro del alcance de $actor (lo usa
     * SeasonTeamPolicy::view): misma regla que scopeVisibleTo().
     */
    public function isVisibleTo(User $actor, SeasonTeam $seasonTeam): bool
    {
        return $this->scopeVisibleTo(SeasonTeam::query(), $actor)->whereKey($seasonTeam->id)->exists();
    }

    /**
     * Competencias en las que $actor puede inscribir equipos.
     */
    public function seasonsFor(User $actor): Collection
    {
        $isOrganizer = $actor->hasRole('organizador') && $actor->can('seasons.manage');

        return Season::query()
            ->with('gamesEdition:id,name,organizer_id')
            ->where('status', '!=', SeasonStatus::Closed->value)
            // Admin: todas las no cerradas. Organizador: las de sus olimpiadas.
            // Delegado (u otro rol): ninguna, ya no inscribe equipos.
            ->unless($actor->hasRole('admin'), fn ($q) => $isOrganizer
                ? $q->whereHas('gamesEdition', fn ($t) => $t->where('organizer_id', $actor->id))
                : $q->whereRaw('1 = 0'))
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Autocomplete de equipo al inscribir: los elegibles salen del catalogo
     * GLOBAL de equipos (crece con el sistema, no con la competencia), por
     * eso se buscan server-side (min. 2 letras, max. 10) en vez de listarse
     * todos. Vacio si $actor no es admin ni organizador de esa olimpiada.
     */
    public function searchTeams(User $actor, Season $season, string $term): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < 2 || ! $this->isManager($actor, $season)) {
            return collect();
        }

        return $this->eligibleTeams($season)
            ->where('name', 'like', "%{$term}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);
    }

    /** Un equipo concreto elegible para $season (el id viene del navegador). */
    public function findEligibleTeam(User $actor, Season $season, int $teamId): Team
    {
        if (! $this->isManager($actor, $season)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador pueden inscribir equipos.');
        }

        return $this->eligibleTeams($season)->findOrFail($teamId);
    }

    /** Activos y aun no inscritos en esa competencia (no se duplica la inscripcion). */
    private function eligibleTeams(Season $season): Builder
    {
        return Team::query()
            ->where('is_active', true)
            ->whereDoesntHave('seasonTeams', fn ($q) => $q->where('season_id', $season->id));
    }

    /** Admin u organizador de la olimpiada de la competencia. */
    public function isManager(User $actor, Season $season): bool
    {
        return $actor->hasRole('admin')
            || ($actor->hasRole('organizador')
                && $actor->can('seasons.manage')
                && $season->gamesEdition->organizer_id === $actor->id);
    }

    private function seasonRejectionReason(Season $season): string
    {
        if ($season->status === SeasonStatus::Closed) {
            return 'La competencia está cerrada: no admite inscripciones.';
        }

        return 'No puedes inscribir equipos en esta competencia.';
    }
}
