<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeasonStatus;
use App\Models\EventParticipant;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Inscripcion de equipos a temporadas (season_team). Reglas confirmadas
 * ("Flujo de permisos" punto 4 de CLAUDE.md):
 *   - Organizador (de ese torneo) y admin: inscriben cualquier equipo, en
 *     Borrador o En curso, y eligen delegado de temporada.
 *   - CAMBIO DE REGLA: el delegado ya NO inscribe (antes lo hacia con sus
 *     equipos en Borrador). No crea ni edita season_team en ningun estado.
 *   - Nadie inscribe en una temporada Cerrada.
 */
class SeasonTeamService
{
    /**
     * @param array{season_id: int, team_id: int, category_id: int|null, delegate_id: int|null, is_active: bool} $data
     */
    public function register(array $data, User $actor): SeasonTeam
    {
        $season = Season::with('tournament')->findOrFail($data['season_id']);
        $team = Team::findOrFail($data['team_id']);

        // Revalidado aqui (no solo en SeasonTeamPolicy): un delegado que
        // llame a save() desde la consola del navegador no inscribe nada.
        if (! $this->isManager($actor, $season)) {
            throw new AuthorizationException('Solo el organizador del torneo o el administrador pueden inscribir equipos.');
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
            throw ValidationException::withMessages(['form.team_id' => 'El equipo ya está inscrito en esta temporada.']);
        }

        $this->ensureValidSeasonDelegate($data['delegate_id'] ?? null);

        return SeasonTeam::create([
            'season_id' => $season->id,
            'team_id' => $team->id,
            'category_id' => $data['category_id'] ?? null,
            'delegate_id' => $data['delegate_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Temporada y equipo NO se cambian: una inscripcion a otra temporada
     * es otra inscripcion (retirar + inscribir). Solo categoria, delegado
     * de temporada y estado.
     *
     * @param array{category_id: int|null, delegate_id: int|null, is_active: bool} $data
     */
    public function update(SeasonTeam $seasonTeam, array $data, User $actor): SeasonTeam
    {
        if (! $this->isManager($actor, $seasonTeam->season)) {
            throw new AuthorizationException('Solo el organizador del torneo o el administrador pueden editar inscripciones.');
        }

        $this->ensureValidSeasonDelegate($data['delegate_id'] ?? null);

        $seasonTeam->update([
            'category_id' => $data['category_id'] ?? null,
            'delegate_id' => $data['delegate_id'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        return $seasonTeam;
    }

    /**
     * - event_participants es polimorfica SIN FK: si el equipo ya fue
     *   programado en algun partido, borrar dejaria participaciones
     *   huerfanas. Se bloquea explicitamente.
     * - El roster (season_team_player) se borra en CASCADE, pero si algun
     *   jugador tiene goles/sanciones/relevos, esas FKs son RESTRICT y
     *   MySQL lo rechaza: se captura y se devuelve un mensaje claro.
     * - season_standings se va en CASCADE (es un dato calculado).
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(SeasonTeam $seasonTeam): bool|string
    {
        if ($seasonTeam->eventParticipants()->exists()) {
            return 'No se puede retirar: el equipo ya está programado en partidos de la temporada. Desactiva la inscripción en su lugar.';
        }

        $rosterIds = $seasonTeam->roster()->pluck('id');

        if ($rosterIds->isNotEmpty()
            && EventParticipant::where('participant_type', 'season_team_player')->whereIn('participant_id', $rosterIds)->exists()) {
            return 'No se puede retirar: jugadores del equipo ya compitieron en la temporada. Desactiva la inscripción en su lugar.';
        }

        try {
            $seasonTeam->delete();
        } catch (QueryException) {
            return 'No se puede retirar: el plantel del equipo tiene historial (goles, sanciones o relevos). Desactiva la inscripción en su lugar.';
        }

        return true;
    }

    /**
     * Temporadas en las que $actor puede inscribir equipos.
     */
    public function seasonsFor(User $actor): Collection
    {
        $isOrganizer = $actor->hasRole('organizador') && $actor->can('seasons.manage');

        return Season::query()
            ->with('tournament:id,name,organizer_id')
            ->where('status', '!=', SeasonStatus::Closed->value)
            // Admin: todas las no cerradas. Organizador: las de sus torneos.
            // Delegado (u otro rol): ninguna, ya no inscribe equipos.
            ->unless($actor->hasRole('admin'), fn ($q) => $isOrganizer
                ? $q->whereHas('tournament', fn ($t) => $t->where('organizer_id', $actor->id))
                : $q->whereRaw('1 = 0'))
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Equipos que $actor puede inscribir en $season (activos y aun no
     * inscritos). Vacio si no es admin ni organizador de ese torneo.
     */
    public function teamsFor(User $actor, Season $season): Collection
    {
        if (! $this->isManager($actor, $season)) {
            return collect();
        }

        return Team::query()
            ->where('is_active', true)
            ->whereDoesntHave('seasonTeams', fn ($q) => $q->where('season_id', $season->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Admin u organizador del torneo de la temporada. */
    public function isManager(User $actor, Season $season): bool
    {
        return $actor->hasRole('admin')
            || ($actor->hasRole('organizador')
                && $actor->can('seasons.manage')
                && $season->tournament->organizer_id === $actor->id);
    }

    private function seasonRejectionReason(Season $season): string
    {
        if ($season->status === SeasonStatus::Closed) {
            return 'La temporada está cerrada: no admite inscripciones.';
        }

        return 'No puedes inscribir equipos en esta temporada.';
    }

    private function ensureValidSeasonDelegate(?int $delegateId): void
    {
        if ($delegateId && ! User::role('delegado')->whereKey($delegateId)->exists()) {
            throw ValidationException::withMessages([
                'form.delegate_id' => 'El delegado de temporada debe ser un usuario con rol delegado.',
            ]);
        }
    }
}
