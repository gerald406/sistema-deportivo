<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\SeasonTeamPlayerStatus;
use App\Models\GameMatch;
use App\Models\SeasonTeamPlayer;
use App\Models\Suspension;
use App\Models\User;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sanciones. Reglas confirmadas (regla 6A, Fase 7):
 *   - Automaticas: al cerrar un partido, cada roja directa (red_card) o
 *     roja por doble amarilla (red_card_indirect) genera una sancion de
 *     1 partido. Las amarillas no se acumulan.
 *   - Manuales: el organizador crea/edita/borra y marca como cumplidas.
 *   - Mientras un jugador tenga sanciones pendientes su ficha de plantel
 *     queda "Suspendido"; al no tener ninguna pendiente vuelve a
 *     "Habilitado". Solo se toca el estado si era Habilitado/Suspendido
 *     (un Lesionado o de Baja conserva su estado).
 */
class SuspensionService
{
    /** Codigos de event_types que generan sancion automatica. */
    public const AUTO_CODES = ['red_card', 'red_card_indirect'];

    /**
     * Genera las sanciones automaticas de un partido cerrado. Idempotente:
     * no duplica si ya existe una sancion del mismo jugador, partido y
     * motivo (p. ej. al re-cerrar tras una reapertura).
     *
     * @return int sanciones creadas
     */
    public function generateForMatch(GameMatch $match): int
    {
        $events = $match->events()
            ->with('eventType:id,code,name')
            ->whereHas('eventType', fn ($q) => $q->whereIn('code', self::AUTO_CODES))
            ->get();

        $created = 0;

        foreach ($events as $event) {
            $suspension = Suspension::firstOrCreate(
                ['match_id' => $match->id, 'season_team_player_id' => $event->season_team_player_id, 'reason' => $event->eventType->name],
                ['season_id' => $match->season_id, 'matches_suspended' => 1, 'is_served' => false],
            );

            if ($suspension->wasRecentlyCreated) {
                $created++;
                $this->syncRosterStatus($event->season_team_player_id);
            }
        }

        return $created;
    }

    /**
     * Borra las sanciones NO cumplidas originadas en un partido (lo usa la
     * reapertura) y reajusta el estado de esos jugadores.
     *
     * @return int sanciones borradas
     */
    public function deleteUnservedForMatch(GameMatch $match): int
    {
        $playerIds = $match->suspensions()->where('is_served', false)->pluck('season_team_player_id')->unique();
        $deleted = $match->suspensions()->where('is_served', false)->delete();

        $playerIds->each(fn ($id) => $this->syncRosterStatus($id));

        return $deleted;
    }

    /**
     * @param array{match_id: int, season_team_player_id: int, reason: string, matches_suspended: int, is_served: bool} $data
     */
    public function register(array $data, User $actor): Suspension
    {
        $match = GameMatch::with('season.tournament')->findOrFail($data['match_id']);
        $this->ensureCanManage($match, $actor);
        $this->ensureValid($match, $data);

        return DB::transaction(function () use ($match, $data) {
            $suspension = Suspension::create([
                'season_id' => $match->season_id,
                'match_id' => $match->id,
                'season_team_player_id' => $data['season_team_player_id'],
                'reason' => trim($data['reason']),
                'matches_suspended' => $data['matches_suspended'],
                'is_served' => (bool) $data['is_served'],
            ]);

            $this->syncRosterStatus($suspension->season_team_player_id);

            return $suspension;
        });
    }

    /** Jugador y partido de origen no cambian: solo motivo, fechas y cumplimiento. */
    public function update(Suspension $suspension, array $data, User $actor): Suspension
    {
        $suspension->load('match.season.tournament');
        $this->ensureCanManage($suspension->match, $actor);

        $matches = (int) $data['matches_suspended'];
        if ($matches < 1 || $matches > 50) {
            throw ValidationException::withMessages(['form.matches_suspended' => 'La sanción debe ser de 1 a 50 partidos.']);
        }

        return DB::transaction(function () use ($suspension, $data, $matches) {
            $suspension->update([
                'reason' => trim($data['reason']),
                'matches_suspended' => $matches,
                'is_served' => (bool) $data['is_served'],
            ]);

            $this->syncRosterStatus($suspension->season_team_player_id);

            return $suspension;
        });
    }

    public function toggleServed(Suspension $suspension, User $actor): Suspension
    {
        $suspension->load('match.season.tournament');
        $this->ensureCanManage($suspension->match, $actor);

        $suspension->update(['is_served' => ! $suspension->is_served]);
        $this->syncRosterStatus($suspension->season_team_player_id);

        return $suspension;
    }

    /**
     * Nada referencia a suspensions: borrar es seguro. Devuelve true o el
     * mensaje de por que no.
     */
    public function delete(Suspension $suspension, User $actor): bool|string
    {
        $suspension->load('match.season.tournament');
        $this->ensureCanManage($suspension->match, $actor);

        $playerId = $suspension->season_team_player_id;
        $suspension->delete();
        $this->syncRosterStatus($playerId);

        return true;
    }

    /** Ajusta el estado de la ficha de plantel segun sus sanciones pendientes. */
    public function syncRosterStatus(int $seasonTeamPlayerId): void
    {
        $entry = SeasonTeamPlayer::find($seasonTeamPlayerId);

        if (! $entry) {
            return;
        }

        $pending = Suspension::where('season_team_player_id', $entry->id)->where('is_served', false)->exists();

        if ($pending && $entry->status === SeasonTeamPlayerStatus::Active) {
            $entry->update(['status' => SeasonTeamPlayerStatus::Suspended->value]);
        } elseif (! $pending && $entry->status === SeasonTeamPlayerStatus::Suspended) {
            $entry->update(['status' => SeasonTeamPlayerStatus::Active->value]);
        }
    }

    private function ensureCanManage(GameMatch $match, User $actor): void
    {
        if (! SeasonAccess::manages($actor, $match->season)) {
            throw new AuthorizationException('Solo el organizador del torneo gestiona las sanciones de esta temporada.');
        }
    }

    private function ensureValid(GameMatch $match, array $data): void
    {
        if ($match->status === MatchStatus::Canceled) {
            throw ValidationException::withMessages(['form.match_id' => 'Un partido cancelado no origina sanciones.']);
        }

        if (! SeasonTeamPlayer::whereKey($data['season_team_player_id'])
            ->whereHas('seasonTeam', fn ($q) => $q->where('season_id', $match->season_id))->exists()) {
            throw ValidationException::withMessages(['form.season_team_player_id' => 'El jugador no pertenece a un plantel de esta temporada.']);
        }

        if (mb_strlen(trim((string) $data['reason'])) < 3) {
            throw ValidationException::withMessages(['form.reason' => 'Indica el motivo de la sanción.']);
        }

        $matches = (int) $data['matches_suspended'];
        if ($matches < 1 || $matches > 50) {
            throw ValidationException::withMessages(['form.matches_suspended' => 'La sanción debe ser de 1 a 50 partidos.']);
        }
    }
}
