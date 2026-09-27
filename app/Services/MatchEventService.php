<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\EventType;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\SeasonTeamPlayer;
use App\Models\User;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Incidencias de un partido (match_events): goles, tarjetas, puntos de
 * bloqueo, etc. Reglas:
 *   - El tipo de evento debe ser del deporte de la temporada.
 *   - El jugador debe PARTICIPAR en el partido: estar en el plantel de uno
 *     de los equipos participantes (modo equipos) o ser uno de los
 *     jugadores participantes (modo individual, ej. ajedrez).
 *   - Solo con el partido Programado: al cerrar el resultado las
 *     incidencias generan sanciones; con resultado cargado ya son
 *     historial (se corrigen reabriendo el partido).
 */
class MatchEventService
{
    /**
     * @param array{match_id: int, season_team_player_id: int, event_type_id: int, minute: int|null} $data
     */
    public function register(array $data, User $actor): MatchEvent
    {
        $match = GameMatch::with('season.tournament')->findOrFail($data['match_id']);
        $this->ensureEditable($match, $actor);
        $this->ensureValid($match, $data);

        return MatchEvent::create([
            'match_id' => $match->id,
            'season_team_player_id' => $data['season_team_player_id'],
            'event_type_id' => $data['event_type_id'],
            'minute' => $data['minute'] ?? null,
        ]);
    }

    /** El partido de una incidencia no cambia (seria otra incidencia). */
    public function update(MatchEvent $event, array $data, User $actor): MatchEvent
    {
        $event->load('match.season.tournament');
        $this->ensureEditable($event->match, $actor);
        $this->ensureValid($event->match, $data);

        $event->update([
            'season_team_player_id' => $data['season_team_player_id'],
            'event_type_id' => $data['event_type_id'],
            'minute' => $data['minute'] ?? null,
        ]);

        return $event;
    }

    /**
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(MatchEvent $event, User $actor): bool|string
    {
        $event->load('match.season.tournament');

        if ($event->match->status !== MatchStatus::Pending) {
            return 'No se puede eliminar: el partido ya tiene resultado. Reábrelo para corregir sus incidencias.';
        }

        if (! SeasonAccess::manages($actor, $event->match->season)) {
            throw new AuthorizationException('Solo el organizador del torneo gestiona las incidencias de este partido.');
        }

        $event->delete();

        return true;
    }

    /** Jugadores que pueden tener incidencias en el partido. */
    public function eligiblePlayers(GameMatch $match): Collection
    {
        $participants = $match->participants()->get(['participant_type', 'participant_id']);
        $teamIds = $participants->where('participant_type', 'season_team')->pluck('participant_id');
        $playerIds = $participants->where('participant_type', 'season_team_player')->pluck('participant_id');

        return SeasonTeamPlayer::with(['player:id,first_name,last_name', 'seasonTeam.team:id,name'])
            ->where(fn ($q) => $q->whereIn('season_team_id', $teamIds)->orWhereIn('id', $playerIds))
            ->get()
            ->sortBy(fn ($e) => $e->seasonTeam->team->name . $e->player->last_name)
            ->values();
    }

    /** Tipos de evento del deporte del partido. */
    public function eventTypesFor(GameMatch $match): Collection
    {
        return EventType::where('sport_id', $match->season->sport_id)->orderBy('name')->get(['id', 'code', 'name']);
    }

    private function ensureEditable(GameMatch $match, User $actor): void
    {
        if (! SeasonAccess::manages($actor, $match->season)) {
            throw new AuthorizationException('Solo el organizador del torneo gestiona las incidencias de este partido.');
        }

        if ($match->status !== MatchStatus::Pending) {
            throw ValidationException::withMessages(['form.match_id' => 'El partido ya tiene resultado: reábrelo para corregir sus incidencias.']);
        }
    }

    private function ensureValid(GameMatch $match, array $data): void
    {
        if (! EventType::whereKey($data['event_type_id'])->where('sport_id', $match->season->sport_id)->exists()) {
            throw ValidationException::withMessages(['form.event_type_id' => 'El tipo de incidencia no pertenece al deporte del partido.']);
        }

        if (! $this->eligiblePlayers($match)->contains('id', (int) $data['season_team_player_id'])) {
            throw ValidationException::withMessages(['form.season_team_player_id' => 'El jugador no participa en este partido.']);
        }

        $minute = $data['minute'] ?? null;

        if ($minute !== null && ($minute < 0 || $minute > 200)) {
            throw ValidationException::withMessages(['form.minute' => 'El minuto debe estar entre 0 y 200.']);
        }
    }
}
