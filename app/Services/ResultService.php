<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BetterDirection;
use App\Enums\MatchModality;
use App\Enums\MatchStatus;
use App\Enums\SeasonStatus;
use App\Enums\SportFormatType;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\MatchReopenLog;
use App\Models\User;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Carga de resultados (event_participants.result_value / position y
 * matches.status) y reapertura auditada de un partido cerrado.
 *
 * Reglas confirmadas (Fase 7):
 *   - Enfrentamiento: el marcador sale de los parciales si los hay; si no,
 *     se ingresa a mano. Si la modalidad es "al mejor de N" los parciales
 *     son sets y el marcador es la cantidad de sets ganados; en cualquier
 *     otro caso (tiempos de futbol, cuartos) se suman los puntos. Sin
 *     empate en modalidades "al mejor de N".
 *     position guarda el desenlace: 1 = ganador, 2 = perdedor, 1/1 = empate.
 *   - Walkover: el ausente queda con position 2 y sin marcador; el
 *     presente con position 1. Los puntos (pts_walkover / pts_win) los
 *     aplica el calculo de la tabla.
 *   - Individual / postas: position se calcula desde result_value segun
 *     discipline.better_direction (asc = menor es mejor: tiempos; desc =
 *     mayor es mejor: distancias). Empates comparten puesto (1, 1, 3).
 *     Sin disciplina (deporte individual sin disciplinas) se asume desc.
 *     Participante sin marca = no clasifica (position NULL).
 *
 * Reapertura: exige SeasonPolicy::reopenMatches. Deja el partido en
 * Programado, guarda un snapshot en match_reopen_logs, limpia marcas y
 * puestos, y borra las sanciones NO cumplidas que origino (se regeneran
 * al volver a cerrarlo). Las incidencias (match_events) se conservan:
 * siguen siendo validas y se pueden corregir antes de re-cerrar.
 */
class ResultService
{
    public function __construct(
        private GameMatchService $matches,
        private MatchPeriodService $periods,
    ) {
    }

    /**
     * Enfrentamiento: $input = ['walkover' => null|'home'|'away' (lado AUSENTE), 'home' => marcador, 'away' => marcador].
     * Individual/posta: $input = ['results' => [event_participant_id => marca|null]].
     */
    public function close(GameMatch $match, array $input, User $actor): void
    {
        $match->load('season.tournament', 'season.sport', 'discipline', 'participants');
        $this->ensureCanManage($match, $actor);

        if ($match->status !== MatchStatus::Pending) {
            throw ValidationException::withMessages(['result' => 'El partido ya tiene resultado. Reábrelo para corregirlo.']);
        }

        if ($match->participants->isEmpty()) {
            throw ValidationException::withMessages(['result' => 'El partido no tiene participantes asignados.']);
        }

        $format = $this->matches->formatFor($match->season, $match->discipline);

        DB::transaction(function () use ($match, $input, $format) {
            $format === SportFormatType::HeadToHead
                ? $this->closeHeadToHead($match, $input)
                : $this->closeRanked($match, $input);

            $this->afterResultChanged($match);
        });
    }

    public function reopen(GameMatch $match, string $reason, User $actor): MatchReopenLog
    {
        $match->load('season.tournament', 'participants', 'periods');

        if (! Gate::forUser($actor)->allows('reopenMatches', $match->season)) {
            throw new AuthorizationException('No tienes permiso para reabrir partidos de esta temporada.');
        }

        if (! in_array($match->status, [MatchStatus::Played, MatchStatus::Walkover], true)) {
            throw ValidationException::withMessages(['reason' => 'Solo se reabre un partido Jugado o con Walkover.']);
        }

        if ($match->season->status === SeasonStatus::Closed) {
            throw ValidationException::withMessages(['reason' => 'La temporada está cerrada: sus partidos no se reabren.']);
        }

        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Explica el motivo de la reapertura (mínimo 5 caracteres).']);
        }

        return DB::transaction(function () use ($match, $reason, $actor) {
            $snapshot = [
                'participants' => $match->participants->map(fn (EventParticipant $p) => [
                    'id' => $p->id, 'type' => $p->participant_type, 'participant_id' => $p->participant_id,
                    'side' => $p->side?->value, 'result_value' => $p->result_value, 'position' => $p->position,
                ])->values()->all(),
                'periods' => $match->periods->map(fn ($p) => [$p->period_number, $p->home_points, $p->away_points])->values()->all(),
            ];

            $suspensionsDeleted = $match->suspensions()->where('is_served', false)->delete();

            $log = MatchReopenLog::create([
                'match_id' => $match->id,
                'user_id' => $actor->id,
                'previous_status' => $match->status->value,
                'previous_state' => $snapshot,
                'events_deleted' => 0,
                'suspensions_deleted' => $suspensionsDeleted,
                'reason' => trim($reason),
            ]);

            $match->participants()->update(['result_value' => null, 'position' => null]);
            $match->update(['status' => MatchStatus::Pending->value]);

            $this->afterResultChanged($match);

            return $log;
        });
    }

    /**
     * Marcador de un enfrentamiento derivado de sus parciales, o null si
     * no tiene parciales (entonces se ingresa a mano).
     *
     * @return array{home: int, away: int}|null
     */
    public function scoreFromPeriods(GameMatch $match): ?array
    {
        $periods = $match->periods()->get();

        if ($periods->isEmpty()) {
            return null;
        }

        if ($this->isBestOf($match)) {
            return [
                'home' => $periods->filter(fn ($p) => $p->home_points > $p->away_points)->count(),
                'away' => $periods->filter(fn ($p) => $p->away_points > $p->home_points)->count(),
            ];
        }

        return $this->periods->totals($match);
    }

    public function isBestOf(GameMatch $match): bool
    {
        return in_array($match->modality, [MatchModality::BestOf1, MatchModality::BestOf3, MatchModality::BestOf5], true);
    }

    // ------------------------------------------------------------------

    /**
     * Punto unico donde se engancha lo que depende de un resultado
     * (sanciones automaticas, tabla, medallero). Ver modulos 14 y 15.
     */
    private function afterResultChanged(GameMatch $match): void
    {
        //
    }

    private function closeHeadToHead(GameMatch $match, array $input): void
    {
        $home = $match->participants->firstWhere('side.value', 'home');
        $away = $match->participants->firstWhere('side.value', 'away');

        if (! $home || ! $away) {
            throw ValidationException::withMessages(['result' => 'El enfrentamiento debe tener local y visitante.']);
        }

        $absent = $input['walkover'] ?? null;

        if (in_array($absent, ['home', 'away'], true)) {
            [$winner, $loser] = $absent === 'home' ? [$away, $home] : [$home, $away];
            $winner->update(['result_value' => null, 'position' => 1]);
            $loser->update(['result_value' => null, 'position' => 2]);
            $match->update(['status' => MatchStatus::Walkover->value]);

            return;
        }

        $score = $this->scoreFromPeriods($match) ?? $this->manualScore($input);

        if ($score['home'] === $score['away'] && $this->isBestOf($match)) {
            throw ValidationException::withMessages(['result' => 'En una modalidad "al mejor de N" no puede haber empate. Revisa los parciales.']);
        }

        $home->update(['result_value' => $score['home'], 'position' => $score['home'] >= $score['away'] ? 1 : 2]);
        $away->update(['result_value' => $score['away'], 'position' => $score['away'] >= $score['home'] ? 1 : 2]);
        $match->update(['status' => MatchStatus::Played->value]);
    }

    /** @return array{home: int, away: int} */
    private function manualScore(array $input): array
    {
        foreach (['home', 'away'] as $side) {
            $v = $input[$side] ?? null;

            if (! is_numeric($v) || (int) $v < 0 || (int) $v != $v) {
                throw ValidationException::withMessages(["result.$side" => 'Ingresa el marcador (entero de 0 o más).']);
            }
        }

        return ['home' => (int) $input['home'], 'away' => (int) $input['away']];
    }

    private function closeRanked(GameMatch $match, array $input): void
    {
        $results = $input['results'] ?? [];
        $values = [];

        foreach ($match->participants as $p) {
            $v = $results[$p->id] ?? null;

            if ($v === null || $v === '') {
                $values[$p->id] = null;

                continue;
            }

            if (! is_numeric($v) || (float) $v < 0) {
                throw ValidationException::withMessages(["results.{$p->id}" => 'La marca debe ser un número de 0 o más.']);
            }

            $values[$p->id] = round((float) $v, 4);
        }

        if (array_filter($values, fn ($v) => $v !== null) === []) {
            throw ValidationException::withMessages(['result' => 'Ingresa la marca de al menos un participante.']);
        }

        $positions = $this->rank($values, $match->discipline?->better_direction ?? BetterDirection::Descending);

        foreach ($match->participants as $p) {
            $p->update(['result_value' => $values[$p->id], 'position' => $positions[$p->id] ?? null]);
        }

        $match->update(['status' => MatchStatus::Played->value]);
    }

    /**
     * Ranking con empates compartidos (1, 1, 3). Los null no clasifican.
     *
     * @param array<int, float|null> $values id => marca
     * @return array<int, int> id => puesto
     */
    public function rank(array $values, BetterDirection $direction): array
    {
        $classified = array_filter($values, fn ($v) => $v !== null);
        $direction === BetterDirection::Ascending ? asort($classified) : arsort($classified);

        $positions = [];
        $place = 0;
        $previous = null;

        foreach (array_keys($classified) as $i => $id) {
            if ($previous === null || $classified[$id] != $previous) {
                $place = $i + 1;
            }

            $positions[$id] = $place;
            $previous = $classified[$id];
        }

        return $positions;
    }

    private function ensureCanManage(GameMatch $match, User $actor): void
    {
        if (! SeasonAccess::manages($actor, $match->season)) {
            throw new AuthorizationException('Solo el organizador del torneo carga los resultados de este partido.');
        }

        if ($match->season->status === SeasonStatus::Closed) {
            throw ValidationException::withMessages(['result' => 'La temporada está cerrada.']);
        }
    }
}
