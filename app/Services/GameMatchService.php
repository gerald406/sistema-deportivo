<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\SeasonStatus;
use App\Enums\SeasonTeamPlayerStatus;
use App\Enums\SportFormatType;
use App\Models\Discipline;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\User;
use App\Models\Venue;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Partidos / pruebas (tabla matches) y sus participantes
 * (event_participants), que se asignan en el mismo modal y se guardan en
 * una sola transaccion.
 *
 * Decisiones confirmadas (Fase 6):
 *   - Formato efectivo = discipline.format_type ?? sport.format_type.
 *   - Enfrentamiento (head_to_head): exactamente 2 participantes, local y
 *     visitante, ambos equipos (season_team) o ambos jugadores
 *     (season_team_player, ej. ajedrez individual): lo elige el partido.
 *   - Ranking individual: 1+ jugadores del plantel. Postas: 1+ equipos.
 *   - La disciplina es obligatoria si el deporte tiene disciplinas; si no
 *     tiene, no se usa. Que la disciplina sea DEL deporte de la temporada
 *     lo valida el trigger trg_matches_before_*_validate_discipline (no se
 *     duplica aqui): su SQLSTATE 45000 se traduce a un error de formulario.
 *   - En enfrentamientos un participante no juega dos partidos de la misma
 *     jornada; en individual/postas si (un atleta corre y salta el mismo dia).
 *   - El resultado (played/walkover) lo carga el modulo de resultados; aqui
 *     el estado solo alterna entre Programado y Cancelado.
 */
class GameMatchService
{
    /**
     * @param array{matchday_id: int, discipline_id: int|null, venue_id: int|null, round_type: string, modality: string|null, scheduled_at: string|null, status?: string} $data
     * @param array<int, array{type: string, id: int, side: string|null, lane: string|null}> $participants
     */
    public function register(array $data, array $participants, User $actor): GameMatch
    {
        $matchday = Matchday::with('season.tournament', 'season.sport')->findOrFail($data['matchday_id']);
        $season = $matchday->season;

        $this->ensureCanSchedule($season, $actor);
        $data = $this->normalizeMatchData($data, $season, $matchday);
        $data['status'] = MatchStatus::Pending->value;

        $format = $this->formatFor($season, $data['discipline_id'] ? Discipline::find($data['discipline_id']) : null);
        $this->validateParticipants($format, $participants, $season, $matchday);

        return $this->persist(function () use ($data, $participants) {
            $match = GameMatch::create($data);
            $this->replaceParticipants($match, $participants);

            return $match;
        });
    }

    public function update(GameMatch $match, array $data, array $participants, User $actor): GameMatch
    {
        $match->load('season.tournament', 'season.sport');
        $season = $match->season;
        $this->ensureCanSchedule($season, $actor);

        $matchday = Matchday::findOrFail($data['matchday_id']);

        if ($matchday->season_id !== $season->id) {
            throw ValidationException::withMessages(['form.matchday_id' => 'La jornada debe ser de la misma temporada del partido.']);
        }

        $data = $this->normalizeMatchData($data, $season, $matchday, $match);

        // Estado: aqui solo Programado <-> Cancelado. Jugado/Walkover los
        // fija (y los revierte, con log) el modulo de resultados.
        $newStatus = MatchStatus::tryFrom($data['status'] ?? '') ?? $match->status;

        if ($match->status !== $newStatus
            && (! in_array($match->status, [MatchStatus::Pending, MatchStatus::Canceled], true)
                || ! in_array($newStatus, [MatchStatus::Pending, MatchStatus::Canceled], true))) {
            throw ValidationException::withMessages(['form.status' => 'El resultado del partido se gestiona desde la carga de resultados.']);
        }
        $data['status'] = $newStatus->value;

        $participantsChanged = $this->participantKeys($participants) !== $this->participantKeys($this->currentParticipants($match));

        if ($participantsChanged) {
            if ($this->hasResultData($match)) {
                throw ValidationException::withMessages(['participants' => 'No se pueden cambiar los participantes: el partido ya tiene resultados, incidencias o relevos cargados.']);
            }

            $format = $this->formatFor($season, $data['discipline_id'] ? Discipline::find($data['discipline_id']) : null);
            $this->validateParticipants($format, $participants, $season, $matchday, $match->id);
        }

        return $this->persist(function () use ($match, $data, $participants, $participantsChanged) {
            $match->update($data);

            if ($participantsChanged) {
                $this->replaceParticipants($match, $participants);
            } else {
                // Mismos participantes: solo se actualizan carril/tablero.
                foreach ($participants as $p) {
                    $match->participants()
                        ->where('participant_type', $p['type'])->where('participant_id', $p['id'])
                        ->update(['lane_or_board' => $p['lane'] ?: null]);
                }
            }

            return $match;
        });
    }

    /**
     * suspensions.match_id es RESTRICT y un partido con resultado es
     * historial: solo se borran partidos sin resultados, incidencias,
     * periodos ni sanciones. Participantes y relevos se van en CASCADE.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(GameMatch $match): bool|string
    {
        if (in_array($match->status, [MatchStatus::Played, MatchStatus::Walkover], true)) {
            return 'No se puede eliminar: el partido ya tiene resultado. Reábrelo desde resultados si hay un error.';
        }

        if ($match->suspensions()->exists()) {
            return 'No se puede eliminar: el partido originó sanciones.';
        }

        if ($this->hasResultData($match)) {
            return 'No se puede eliminar: el partido tiene resultados, incidencias o relevos cargados.';
        }

        try {
            $match->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: el partido tiene historial asociado. Cancélalo en su lugar.';
        }

        return true;
    }

    public function formatFor(Season $season, ?Discipline $discipline): SportFormatType
    {
        return $discipline?->format_type ?? $season->sport->format_type;
    }

    /**
     * Partidos cuyo formato EFECTIVO es $format: el de la disciplina, o el
     * del deporte si el partido no tiene disciplina (mismo criterio que
     * formatFor, pero en SQL para listados).
     */
    public function queryByFormat(SportFormatType $format): Builder
    {
        return GameMatch::query()->where(fn ($q) => $q
            ->whereHas('discipline', fn ($d) => $d->where('format_type', $format->value))
            ->orWhere(fn ($q) => $q->where(fn ($q) => $q->whereNull('discipline_id')
                ->orWhereHas('discipline', fn ($d) => $d->whereNull('format_type')))
                ->whereHas('season.sport', fn ($s) => $s->where('format_type', $format->value))));
    }

    /** true si el deporte de la temporada exige disciplina (regla 3A). */
    public function requiresDiscipline(Season $season): bool
    {
        return $season->sport->disciplines()->exists();
    }

    /** Participantes actuales en el mismo formato que recibe register/update. */
    public function currentParticipants(GameMatch $match): array
    {
        return $match->participants()
            ->orderByRaw("FIELD(side, 'home', 'away')")->orderBy('id')
            ->get()
            ->map(fn (EventParticipant $p) => [
                'type' => $p->participant_type,
                'id' => $p->participant_id,
                'side' => $p->side?->value,
                'lane' => $p->lane_or_board,
            ])->all();
    }

    // ------------------------------------------------------------------

    private function ensureCanSchedule(Season $season, User $actor): void
    {
        if (! SeasonAccess::manages($actor, $season)) {
            throw new AuthorizationException('Solo el organizador del torneo gestiona los partidos de esta temporada.');
        }

        if ($season->status === SeasonStatus::Closed) {
            throw ValidationException::withMessages(['form.matchday_id' => 'La temporada está cerrada: no admite cambios en sus partidos.']);
        }
    }

    private function normalizeMatchData(array $data, Season $season, Matchday $matchday, ?GameMatch $current = null): array
    {
        $data['season_id'] = $season->id; // siempre derivado de la jornada
        $data['matchday_id'] = $matchday->id;

        foreach (['discipline_id', 'venue_id', 'modality', 'scheduled_at'] as $f) {
            $data[$f] = ($data[$f] ?? '') === '' ? null : $data[$f];
        }

        $requires = $this->requiresDiscipline($season);

        if ($requires && ! $data['discipline_id']) {
            throw ValidationException::withMessages(['form.discipline_id' => "El deporte {$season->sport->name} exige elegir una disciplina."]);
        }

        if (! $requires && $data['discipline_id']) {
            throw ValidationException::withMessages(['form.discipline_id' => "El deporte {$season->sport->name} no usa disciplinas."]);
        }

        // Una sede inactiva no se asigna, pero si el partido ya la tenia se conserva.
        if ($data['venue_id'] && (int) $data['venue_id'] !== $current?->venue_id
            && ! Venue::whereKey($data['venue_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['form.venue_id' => 'La sede está inactiva.']);
        }

        if ($data['scheduled_at']) {
            $day = Carbon::parse($data['scheduled_at'])->startOfDay();

            if (($matchday->start_date && $day->lt($matchday->start_date)) || ($matchday->end_date && $day->gt($matchday->end_date))) {
                throw ValidationException::withMessages(['form.scheduled_at' => 'La fecha del partido está fuera de las fechas de la jornada.']);
            }
        }

        return $data;
    }

    private function validateParticipants(SportFormatType $format, array $participants, Season $season, Matchday $matchday, ?int $ignoreMatchId = null): void
    {
        $fail = fn (string $msg) => throw ValidationException::withMessages(['participants' => $msg]);
        $types = array_unique(array_column($participants, 'type'));
        $keys = array_map(fn ($p) => $p['type'] . ':' . $p['id'], $participants);

        if (count($keys) !== count(array_unique($keys))) {
            $fail('Un participante está repetido.');
        }

        // Carril unico solo en individual/postas: en un enfrentamiento
        // ambos lados comparten el mismo tablero (ajedrez: blancas y negras
        // en "Tablero 1").
        $lanes = array_filter(array_map(fn ($p) => trim((string) ($p['lane'] ?? '')), $participants), fn ($l) => $l !== '');
        if ($format !== SportFormatType::HeadToHead && count($lanes) !== count(array_unique($lanes))) {
            $fail('Hay carriles repetidos.');
        }

        match ($format) {
            SportFormatType::HeadToHead => (count($participants) !== 2 || count($types) !== 1
                || array_column($participants, 'side') != ['home', 'away'])
                    ? $fail('Un enfrentamiento necesita un local y un visitante del mismo tipo (dos equipos o dos jugadores).') : null,
            SportFormatType::IndividualRanked => ($participants === [] || $types !== ['season_team_player'])
                ? $fail('Una prueba individual necesita al menos un jugador.') : null,
            SportFormatType::TeamRelay => ($participants === [] || $types !== ['season_team'])
                ? $fail('Una posta necesita al menos un equipo.') : null,
        };

        foreach ($participants as $p) {
            if ($p['type'] === 'season_team') {
                $ok = SeasonTeam::whereKey($p['id'])->where('season_id', $season->id)->where('is_active', true)->exists();
                $ok || $fail('Todos los equipos deben estar inscritos (y activos) en la temporada.');
            } else {
                $ok = SeasonTeamPlayer::whereKey($p['id'])->where('status', SeasonTeamPlayerStatus::Active->value)
                    ->whereHas('seasonTeam', fn ($q) => $q->where('season_id', $season->id)->where('is_active', true))->exists();
                $ok || $fail('Todos los jugadores deben estar habilitados en el plantel de un equipo de la temporada.');
            }
        }

        // Regla 4A: en enfrentamientos nadie juega dos partidos de la misma jornada.
        if ($format === SportFormatType::HeadToHead) {
            foreach ($participants as $p) {
                $busy = EventParticipant::where('participant_type', $p['type'])->where('participant_id', $p['id'])
                    ->whereHas('match', fn ($q) => $q->where('matchday_id', $matchday->id)
                        ->where('status', '!=', MatchStatus::Canceled->value)
                        ->when($ignoreMatchId, fn ($q) => $q->whereKeyNot($ignoreMatchId)))
                    ->exists();

                $busy && $fail('Un participante ya tiene otro partido en esta jornada.');
            }
        }
    }

    private function replaceParticipants(GameMatch $match, array $participants): void
    {
        $match->participants()->delete();

        foreach ($participants as $p) {
            $match->participants()->create([
                'participant_type' => $p['type'],
                'participant_id' => $p['id'],
                'side' => $p['side'] ?: null,
                'lane_or_board' => ($p['lane'] ?? '') !== '' ? $p['lane'] : null,
            ]);
        }
    }

    private function hasResultData(GameMatch $match): bool
    {
        return $match->participants()->where(fn ($q) => $q->whereNotNull('result_value')->orWhereNotNull('position'))->exists()
            || $match->participants()->whereHas('lineups')->exists()
            || $match->events()->exists()
            || $match->periods()->exists();
    }

    private function participantKeys(array $participants): array
    {
        $keys = array_map(fn ($p) => $p['type'] . ':' . $p['id'] . ':' . ($p['side'] ?? ''), $participants);
        sort($keys);

        return $keys;
    }

    /**
     * Ejecuta la escritura en una transaccion y traduce el SIGNAL del
     * trigger de disciplina/deporte (SQLSTATE 45000) a un error de
     * formulario, en vez de un 500.
     */
    private function persist(callable $write): GameMatch
    {
        try {
            return DB::transaction($write);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '45000') {
                throw ValidationException::withMessages([
                    'form.discipline_id' => $e->errorInfo[2] ?? 'La disciplina no pertenece al deporte de la temporada.',
                ]);
            }

            throw $e;
        }
    }
}
