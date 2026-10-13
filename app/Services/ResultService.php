<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BetterDirection;
use App\Enums\MatchModality;
use App\Enums\MatchStatus;
use App\Enums\SeasonStatus;
use App\Enums\SportFormatType;
use App\Enums\TiebreakType;
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
 *   - Partido de llave (matches.season_stage_id): no admite empate. Si el
 *     marcador queda igualado hay que indicar la via del desempate
 *     (tiempo extra, penales, tiros libres o sorteo) y el lado ganador; el
 *     marcador del desempate es opcional. El sistema registra quien gano
 *     y como, no simula el desempate. Al cerrar, BracketService::advance()
 *     lleva al ganador a su siguiente partido.
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
        private SuspensionService $suspensions,
        private StandingService $standings,
        private AwardService $awards,
        private BracketService $brackets,
    ) {
    }

    /**
     * Enfrentamiento: $input = ['walkover' => null|'home'|'away' (lado AUSENTE), 'home' => marcador, 'away' => marcador].
     *   En partidos de llave empatados, ademas: 'tiebreak_type' (TiebreakType), 'tiebreak_winner_side'
     *   ('home'|'away') y, opcionales, 'tiebreak_home' y 'tiebreak_away'.
     * Individual/posta: $input = ['results' => [event_participant_id => marca|null]].
     */
    public function close(GameMatch $match, array $input, User $actor): void
    {
        $match->load('season.gamesEdition', 'season.sport', 'discipline', 'participants');
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
        $match->load('season.gamesEdition', 'participants', 'periods');

        if (! Gate::forUser($actor)->allows('reopenMatches', $match->season)) {
            throw new AuthorizationException('No tienes permiso para reabrir partidos de esta competencia.');
        }

        if (! in_array($match->status, [MatchStatus::Played, MatchStatus::Walkover], true)) {
            throw ValidationException::withMessages(['reason' => 'Solo se reabre un partido Jugado o con Walkover.']);
        }

        if ($match->season->status === SeasonStatus::Closed) {
            throw ValidationException::withMessages(['reason' => 'La competencia está cerrada: sus partidos no se reabren.']);
        }

        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Explica el motivo de la reapertura (mínimo 5 caracteres).']);
        }

        // Llave: si la ronda siguiente ya se jugo, primero se reabre esa.
        $this->brackets->ensureCanReopen($match);

        return DB::transaction(function () use ($match, $reason, $actor) {
            $snapshot = [
                'participants' => $match->participants->map(fn (EventParticipant $p) => [
                    'id' => $p->id, 'type' => $p->participant_type, 'participant_id' => $p->participant_id,
                    'side' => $p->side?->value, 'result_value' => $p->result_value, 'position' => $p->position,
                ])->values()->all(),
                'periods' => $match->periods->map(fn ($p) => [$p->period_number, $p->home_points, $p->away_points])->values()->all(),
            ];

            $suspensionsDeleted = $this->suspensions->deleteUnservedForMatch($match);

            $log = MatchReopenLog::create([
                'match_id' => $match->id,
                'user_id' => $actor->id,
                'previous_status' => $match->status->value,
                'previous_state' => $snapshot,
                'events_deleted' => 0,
                'suspensions_deleted' => $suspensionsDeleted,
                'reason' => trim($reason),
            ]);

            $match->participants()->update(['result_value' => null, 'position' => null, 'tiebreak_score' => null]);
            $match->update(['status' => MatchStatus::Pending->value, 'tiebreak_type' => null]);

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
        // Sanciones automaticas (regla 6A): solo al CERRAR. En la
        // reapertura ya se borraron las pendientes (deleteUnservedForMatch).
        if (in_array($match->status, [MatchStatus::Played, MatchStatus::Walkover], true)) {
            $this->suspensions->generateForMatch($match);
        }

        // Llave: el ganador (y el perdedor, si hay tercer puesto) pasa a su
        // partido siguiente; al reabrir, se le retira.
        $this->brackets->advance($match);

        // Tabla y medallero: recalculo SINCRONO dentro de la misma
        // transaccion (al cerrar y al reabrir). Por que no Observer ni Job:
        // ver el docblock de StandingService.
        $this->standings->recalculateSeason($match->season);

        if ($match->season->games_edition_id) {
            $this->awards->rebuild($match->season->gamesEdition);
        }
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

        $tied = $score['home'] === $score['away'];
        $tiebreak = $this->tiebreakFrom($input);
        $inBracket = $match->season_stage_id !== null;

        if ($tiebreak !== null && ! $inBracket) {
            throw ValidationException::withMessages(['tiebreak' => 'El desempate solo se registra en partidos de llave.']);
        }

        if ($tiebreak !== null && ! $tied) {
            throw ValidationException::withMessages(['tiebreak' => 'El marcador no está empatado: no corresponde desempate.']);
        }

        if ($inBracket && $tied && $tiebreak === null) {
            throw ValidationException::withMessages(['tiebreak' => 'En un partido de llave no puede haber empate: indica cómo se desempató y quién ganó.']);
        }

        if ($tiebreak !== null) {
            $homeWins = $tiebreak['winner'] === 'home';

            $home->update(['result_value' => $score['home'], 'position' => $homeWins ? 1 : 2, 'tiebreak_score' => $tiebreak['home']]);
            $away->update(['result_value' => $score['away'], 'position' => $homeWins ? 2 : 1, 'tiebreak_score' => $tiebreak['away']]);
            $match->update(['status' => MatchStatus::Played->value, 'tiebreak_type' => $tiebreak['type']->value]);

            return;
        }

        $home->update(['result_value' => $score['home'], 'position' => $score['home'] >= $score['away'] ? 1 : 2]);
        $away->update(['result_value' => $score['away'], 'position' => $score['away'] >= $score['home'] ? 1 : 2]);
        $match->update(['status' => MatchStatus::Played->value]);
    }

    /**
     * Desempate enviado con el resultado, o null si no se envio ninguno.
     *
     * @return array{type: TiebreakType, winner: string, home: ?int, away: ?int}|null
     */
    private function tiebreakFrom(array $input): ?array
    {
        $raw = $input['tiebreak_type'] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        $type = TiebreakType::tryFrom((string) $raw)
            ?? throw ValidationException::withMessages(['tiebreak' => 'La vía de desempate no es válida.']);

        $winner = $input['tiebreak_winner_side'] ?? null;

        if (! in_array($winner, ['home', 'away'], true)) {
            throw ValidationException::withMessages(['tiebreak' => 'Indica quién ganó el desempate.']);
        }

        $scores = [];

        foreach (['home', 'away'] as $side) {
            $value = $input["tiebreak_{$side}"] ?? null;

            if ($value === null || $value === '') {
                $scores[$side] = null;

                continue;
            }

            if (! is_numeric($value) || (int) $value < 0 || (int) $value != $value) {
                throw ValidationException::withMessages(['tiebreak' => 'El marcador del desempate debe ser un entero de 0 o más.']);
            }

            $scores[$side] = (int) $value;
        }

        // El marcador del desempate es opcional (un sorteo no lo tiene),
        // pero si se anota debe estar completo y dar por ganador al lado elegido.
        if (($scores['home'] === null) !== ($scores['away'] === null)) {
            throw ValidationException::withMessages(['tiebreak' => 'Anota el marcador del desempate de ambos lados, o de ninguno.']);
        }

        if ($scores['home'] !== null) {
            $loser = $winner === 'home' ? 'away' : 'home';

            if ($scores[$winner] <= $scores[$loser]) {
                throw ValidationException::withMessages(['tiebreak' => 'El marcador del desempate no coincide con el ganador indicado.']);
            }
        }

        return ['type' => $type, 'winner' => $winner, 'home' => $scores['home'], 'away' => $scores['away']];
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
            throw new AuthorizationException('Solo el organizador de la olimpiada carga los resultados de este partido.');
        }

        if ($match->season->status === SeasonStatus::Closed) {
            throw ValidationException::withMessages(['result' => 'La competencia está cerrada.']);
        }
    }
}
