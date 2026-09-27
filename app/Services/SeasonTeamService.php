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
 * Inscripcion de equipos a temporadas (season_team). Reglas confirmadas:
 *   - Delegado: inscribe solo SUS equipos (teams.delegate_id), solo en
 *     temporadas en Borrador, y no elige delegado de temporada.
 *   - Organizador (de ese torneo) y admin: cualquier equipo, en Borrador
 *     o En curso.
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

        if (! $this->seasonsFor($actor)->contains('id', $season->id)) {
            throw ValidationException::withMessages([
                'form.season_id' => $this->seasonRejectionReason($season, $actor),
            ]);
        }

        if (! $this->isManager($actor, $season)) {
            // Delegado: solo sus equipos y sin delegado de temporada propio.
            if ($team->delegate_id !== $actor->id) {
                throw new AuthorizationException('Solo puedes inscribir equipos de los que eres delegado.');
            }

            $data['delegate_id'] = null;
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
    public function update(SeasonTeam $seasonTeam, array $data): SeasonTeam
    {
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
        $isDelegate = $actor->hasRole('delegado') && $actor->can('teams.manage');
        $isOrganizer = $actor->hasRole('organizador') && $actor->can('seasons.manage');
        $ownTournament = fn ($t) => $t->where('organizer_id', $actor->id);

        return Season::query()
            ->with('tournament:id,name,organizer_id')
            ->where('status', '!=', SeasonStatus::Closed->value)
            ->unless($actor->hasRole('admin'), function ($q) use ($isDelegate, $isOrganizer, $ownTournament) {
                $q->where(function ($q) use ($isDelegate, $isOrganizer, $ownTournament) {
                    // Borrador: el delegado en cualquier torneo; el organizador en los suyos.
                    $q->where(function ($q) use ($isDelegate, $isOrganizer, $ownTournament) {
                        $q->where('status', SeasonStatus::Draft->value)
                            ->where(function ($q) use ($isDelegate, $isOrganizer, $ownTournament) {
                                $q->whereRaw($isDelegate ? '1 = 1' : '1 = 0')
                                    ->when($isOrganizer, fn ($q) => $q->orWhereHas('tournament', $ownTournament));
                            });
                    });

                    // En curso: solo el organizador de ese torneo.
                    if ($isOrganizer) {
                        $q->orWhere(fn ($q) => $q->where('status', SeasonStatus::Active->value)
                            ->whereHas('tournament', $ownTournament));
                    }
                });
            })
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Equipos que $actor puede inscribir en $season (activos y aun no
     * inscritos). El delegado solo ve los suyos, salvo que sea organizador
     * de ese torneo.
     */
    public function teamsFor(User $actor, Season $season): Collection
    {
        return Team::query()
            ->where('is_active', true)
            ->whereDoesntHave('seasonTeams', fn ($q) => $q->where('season_id', $season->id))
            ->when(! $this->isManager($actor, $season), fn ($q) => $q->where('delegate_id', $actor->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Admin u organizador del torneo de la temporada. */
    public function isManager(User $actor, Season $season): bool
    {
        return $actor->hasRole('admin')
            || ($actor->hasRole('organizador') && $season->tournament->organizer_id === $actor->id);
    }

    private function seasonRejectionReason(Season $season, User $actor): string
    {
        if ($season->status === SeasonStatus::Closed) {
            return 'La temporada está cerrada: no admite inscripciones.';
        }

        if ($season->status === SeasonStatus::Active) {
            return 'La temporada ya está en curso: solo el organizador del torneo puede inscribir equipos.';
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
