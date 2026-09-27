<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\SeasonTeamPlayerStatus;
use App\Enums\SportFormatType;
use App\Models\EventParticipant;
use App\Models\SeasonTeamPlayer;
use App\Models\User;
use App\Policies\EventLineupPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orden de relevistas (event_lineups) de un equipo en una posta.
 *
 * Solo aplica si el formato EFECTIVO del partido es team_relay
 * (discipline.format_type ?? sport.format_type): ningun deporte es
 * team_relay por si mismo; la posta es una disciplina (ej. "Posta 4x100"
 * de Atletismo). Se usa el mismo criterio que GameMatchService::formatFor.
 *
 * Reglas: relevistas del plantel del equipo, habilitados, sin repetir;
 * tramos 1..N consecutivos (N <= MAX_LEGS); solo con el partido
 * Programado (con resultado ya es historial).
 */
class EventLineupService
{
    public const MAX_LEGS = 8;

    public function __construct(private GameMatchService $matches)
    {
    }

    /**
     * Reemplaza el orden de relevos del equipo. $runnerIds en orden de
     * tramo: [0] corre el tramo 1, [1] el tramo 2, etc.
     *
     * @param array<int, int> $runnerIds season_team_player ids
     */
    public function syncLineup(EventParticipant $participant, array $runnerIds, User $actor): void
    {
        $this->ensureEditable($participant, $actor);

        $runnerIds = array_values(array_map('intval', $runnerIds));

        if ($runnerIds === []) {
            throw ValidationException::withMessages(['legs' => 'Indica al menos un relevista.']);
        }

        if (count($runnerIds) > self::MAX_LEGS) {
            throw ValidationException::withMessages(['legs' => 'Una posta admite como máximo ' . self::MAX_LEGS . ' tramos.']);
        }

        if (count($runnerIds) !== count(array_unique($runnerIds))) {
            throw ValidationException::withMessages(['legs' => 'Un relevista no puede correr dos tramos.']);
        }

        $valid = SeasonTeamPlayer::whereIn('id', $runnerIds)
            ->where('season_team_id', $participant->participant_id)
            ->where('status', SeasonTeamPlayerStatus::Active->value)
            ->count();

        if ($valid !== count($runnerIds)) {
            throw ValidationException::withMessages(['legs' => 'Todos los relevistas deben estar habilitados en el plantel de este equipo.']);
        }

        DB::transaction(function () use ($participant, $runnerIds) {
            $participant->lineups()->delete();

            foreach ($runnerIds as $i => $id) {
                $participant->lineups()->create(['season_team_player_id' => $id, 'leg_order' => $i + 1]);
            }
        });
    }

    /**
     * Vacia el orden de relevos del equipo (las filas no son historial
     * mientras el partido siga Programado).
     *
     * Devuelve true o el mensaje exacto de por que no se pudo.
     */
    public function clear(EventParticipant $participant, User $actor): bool|string
    {
        try {
            $this->ensureEditable($participant, $actor);
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->first();
        }

        $participant->lineups()->delete();

        return true;
    }

    /**
     * Participantes de tipo equipo en partidos cuyo formato efectivo es
     * posta (discipline.format_type = team_relay, o el deporte si el
     * partido no tiene disciplina).
     */
    public function relayParticipantsQuery(): Builder
    {
        return EventParticipant::query()
            ->where('participant_type', 'season_team')
            ->whereHas('match', fn ($m) => $m->where(fn ($q) => $q
                ->whereHas('discipline', fn ($d) => $d->where('format_type', SportFormatType::TeamRelay->value))
                ->orWhere(fn ($q) => $q->whereNull('discipline_id')
                    ->whereHas('season.sport', fn ($s) => $s->where('format_type', SportFormatType::TeamRelay->value)))));
    }

    private function ensureEditable(EventParticipant $participant, User $actor): void
    {
        $participant->load('match.season.tournament', 'match.season.sport', 'match.discipline', 'participant');
        $match = $participant->match;

        if ($participant->participant_type !== 'season_team'
            || $this->matches->formatFor($match->season, $match->discipline) !== SportFormatType::TeamRelay) {
            throw ValidationException::withMessages(['legs' => 'El orden de relevos solo aplica a equipos en pruebas de posta.']);
        }

        if (! EventLineupPolicy::managesParticipant($actor, $participant)) {
            throw new AuthorizationException('Solo el delegado del equipo o el organizador del torneo cargan estos relevos.');
        }

        if ($match->status !== MatchStatus::Pending) {
            throw ValidationException::withMessages(['legs' => 'La posta ya no está programada: sus relevos no se modifican.']);
        }
    }
}
