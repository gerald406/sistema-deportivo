<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\SeasonTeamPlayerStatus;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\SeasonTeamPlayer;
use App\Models\Suspension;
use App\Models\User;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sanciones. Reglas confirmadas (regla 6A, Fase 7) y reglas de la olimpiada
 * que son DATOS (games_editions; NULL = no se aplica):
 *   - Automaticas: al cerrar un partido, cada roja directa (red_card) o
 *     roja por doble amarilla (red_card_indirect) genera una sancion de
 *     1 partido. Si el expulsado es capitan y la olimpiada define
 *     captain_red_matches, la sancion dura ese numero de partidos.
 *   - Amarillas: solo se acumulan si la olimpiada define
 *     yellow_cards_limit. Cada vez que el total de amarillas de un
 *     participante en la competencia (en partidos con resultado) completa
 *     otro multiplo del limite, recibe 1 partido de sancion. Las de otra
 *     competencia no cuentan.
 *   - Manuales: el organizador crea/edita/borra y marca como cumplidas.
 *   - Mientras un participante tenga sanciones pendientes su ficha de plantel
 *     queda "Suspendido"; al no tener ninguna pendiente vuelve a
 *     "Habilitado". Solo se toca el estado si era Habilitado/Suspendido
 *     (un Lesionado o de Baja conserva su estado).
 */
class SuspensionService
{
    /** Codigos de event_types que generan sancion automatica. */
    public const AUTO_CODES = ['red_card', 'red_card_indirect'];

    public const YELLOW_CODE = 'yellow_card';

    public const YELLOW_ACCUMULATION_REASON = 'Acumulación de tarjetas amarillas';

    /**
     * Genera las sanciones automaticas de un partido cerrado. Idempotente:
     * no duplica si ya existe una sancion del mismo participante, partido y
     * motivo (p. ej. al re-cerrar tras una reapertura).
     *
     * @return int sanciones creadas
     */
    public function generateForMatch(GameMatch $match): int
    {
        $match->loadMissing('season.gamesEdition');
        $edition = $match->season->gamesEdition;

        $events = $match->events()
            ->with('eventType:id,code,name', 'seasonTeamPlayer:id,is_captain')
            ->whereHas('eventType', fn ($q) => $q->whereIn('code', self::AUTO_CODES))
            ->get();

        $created = 0;

        foreach ($events as $event) {
            $matches = $event->seasonTeamPlayer?->is_captain && $edition?->captain_red_matches
                ? $edition->captain_red_matches
                : 1;

            $suspension = Suspension::firstOrCreate(
                ['match_id' => $match->id, 'season_team_player_id' => $event->season_team_player_id, 'reason' => $event->eventType->name],
                ['season_id' => $match->season_id, 'matches_suspended' => $matches, 'is_served' => false],
            );

            if ($suspension->wasRecentlyCreated) {
                $created++;
                $this->syncRosterStatus($event->season_team_player_id);
            }
        }

        return $created + $this->generateForYellowCards($match, $edition?->yellow_cards_limit);
    }

    /**
     * Sancion por acumulacion de amarillas. Se compara lo que el
     * participante DEBE (total de amarillas / limite) con las sanciones de
     * este motivo que ya tiene en la competencia: asi no duplica al
     * re-cerrar un partido ni depende del orden en que se cierren.
     */
    private function generateForYellowCards(GameMatch $match, ?int $limit): int
    {
        if ($limit === null || $limit < 1) {
            return 0;
        }

        $isYellow = fn ($q) => $q->where('code', self::YELLOW_CODE);
        $playerIds = $match->events()->whereHas('eventType', $isYellow)->pluck('season_team_player_id')->unique();
        $created = 0;

        foreach ($playerIds as $playerId) {
            $total = MatchEvent::where('season_team_player_id', $playerId)
                ->whereHas('eventType', $isYellow)
                ->whereHas('match', fn ($q) => $q->where('season_id', $match->season_id)
                    ->whereIn('status', [MatchStatus::Played->value, MatchStatus::Walkover->value]))
                ->count();

            $existing = Suspension::where('season_id', $match->season_id)
                ->where('season_team_player_id', $playerId)
                ->where('reason', self::YELLOW_ACCUMULATION_REASON)
                ->count();

            if (intdiv($total, $limit) <= $existing) {
                continue;
            }

            $suspension = Suspension::firstOrCreate(
                ['match_id' => $match->id, 'season_team_player_id' => $playerId, 'reason' => self::YELLOW_ACCUMULATION_REASON],
                ['season_id' => $match->season_id, 'matches_suspended' => 1, 'is_served' => false],
            );

            if ($suspension->wasRecentlyCreated) {
                $created++;
                $this->syncRosterStatus($playerId);
            }
        }

        return $created;
    }

    /**
     * Borra las sanciones NO cumplidas originadas en un partido (lo usa la
     * reapertura) y reajusta el estado de esos participantes.
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
        $match = GameMatch::with('season.gamesEdition')->findOrFail($data['match_id']);
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

    /** Participante y partido de origen no cambian: solo motivo, fechas y cumplimiento. */
    public function update(Suspension $suspension, array $data, User $actor): Suspension
    {
        $suspension->load('match.season.gamesEdition');
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
        $suspension->load('match.season.gamesEdition');
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
        $suspension->load('match.season.gamesEdition');
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
            throw new AuthorizationException('Solo el organizador de la olimpiada gestiona las sanciones de esta competencia.');
        }
    }

    private function ensureValid(GameMatch $match, array $data): void
    {
        if ($match->status === MatchStatus::Canceled) {
            throw ValidationException::withMessages(['form.match_id' => 'Un partido cancelado no origina sanciones.']);
        }

        if (! SeasonTeamPlayer::whereKey($data['season_team_player_id'])
            ->whereHas('seasonTeam', fn ($q) => $q->where('season_id', $match->season_id))->exists()) {
            throw ValidationException::withMessages(['form.season_team_player_id' => 'El participante no pertenece a un plantel de esta competencia.']);
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
