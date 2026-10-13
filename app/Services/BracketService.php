<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\ParticipantSide;
use App\Enums\RoundType;
use App\Enums\SportFormatType;
use App\Enums\StageMode;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\SeasonStage;
use App\Models\SeasonTeam;
use App\Models\User;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Llave de eliminacion simple de una fase (Art. 8 y 10 de las bases).
 *
 * Con N participantes en el orden que da el organizador (C12: las bases no
 * dicen quien descansa en una serie de 5 o de 3, asi que lo decide el
 * orden): la llave tiene el tamano de la menor potencia de 2 >= N y los
 * primeros de la lista reciben los byes. El 1.o se cruza con el ultimo,
 * el 2.o con el penultimo, etc., repartidos para que los primeros no se
 * encuentren hasta el final. Un cruce con bye no crea partido: el
 * participante ya aparece en la ronda siguiente.
 *
 * Se crean de una vez los partidos de todas las rondas, enlazados por
 * next_match_id; advance() mueve al ganador (y al perdedor, si hay partido
 * por el tercer puesto) cuando se cierra un resultado.
 */
class BracketService
{
    public const REOPEN_BLOCKED = 'La ronda siguiente ya se jugó: reábrela primero.';

    public function __construct(private GameMatchService $matches)
    {
    }

    /**
     * @param list<int|string> $seasonTeamIdsInSeedOrder inscripciones (season_team) de la competencia
     * @return Collection<int, GameMatch> partidos creados
     */
    public function generate(SeasonStage $stage, array $seasonTeamIdsInSeedOrder, User $actor): Collection
    {
        $stage->loadMissing('season.gamesEdition', 'season.sport', 'season.discipline');
        $season = $stage->season;

        $this->ensureManages($actor, $stage);

        $fail = fn (string $message) => throw ValidationException::withMessages(['bracket' => $message]);

        if ($stage->mode !== StageMode::Matches) {
            $fail('Esta fase es de podio directo: no lleva llave.');
        }

        if ($this->matches->formatFor($season, $season->discipline) !== SportFormatType::HeadToHead) {
            $fail('Solo los deportes de enfrentamiento se juegan con llave.');
        }

        if ($stage->matches()->exists()) {
            $fail('La fase ya tiene partidos. Borra la llave antes de generarla de nuevo.');
        }

        $seeds = array_values(array_map('intval', $seasonTeamIdsInSeedOrder));

        if (count($seeds) < 2) {
            $fail('Una llave necesita al menos 2 participantes.');
        }

        if (count($seeds) !== count(array_unique($seeds))) {
            $fail('Un participante está repetido.');
        }

        $valid = SeasonTeam::whereIn('id', $seeds)->where('season_id', $season->id)->where('is_active', true)->count();

        if ($valid !== count($seeds)) {
            $fail('Todos los participantes deben estar inscritos (y activos) en esta competencia.');
        }

        return DB::transaction(fn () => $this->build($stage, $seeds));
    }

    /**
     * Borra la llave de una fase para poder generarla de nuevo. Solo si
     * ningun partido tiene resultado, incidencias ni parciales.
     * Devuelve true o el mensaje exacto para el toast.
     */
    public function clear(SeasonStage $stage, User $actor): bool|string
    {
        $stage->loadMissing('season.gamesEdition');
        $this->ensureManages($actor, $stage);

        $matches = $stage->matches()->get();

        foreach ($matches as $match) {
            if ($match->status !== MatchStatus::Pending || $match->events()->exists() || $match->periods()->exists() || $match->suspensions()->exists()) {
                return 'No se puede borrar la llave: ya hay partidos con resultado, incidencias o sanciones.';
            }
        }

        DB::transaction(function () use ($matches) {
            // Primero se sueltan los enlaces entre partidos (FK a la misma tabla).
            GameMatch::whereIn('id', $matches->pluck('id'))->update(['next_match_id' => null, 'loser_next_match_id' => null]);
            GameMatch::whereIn('id', $matches->pluck('id'))->delete();
        });

        return true;
    }

    /**
     * Lleva al ganador (y al perdedor) de un partido de llave a su partido
     * siguiente, o los retira si el partido volvio a Programado. Lo llama
     * ResultService::afterResultChanged().
     */
    public function advance(GameMatch $match): void
    {
        if ($match->season_stage_id === null) {
            return;
        }

        $match->load('participants');
        $closed = in_array($match->status, [MatchStatus::Played, MatchStatus::Walkover], true);

        $winner = $closed ? $match->participants->firstWhere('position', 1) : null;
        $loser = $closed ? $match->participants->firstWhere('position', 2) : null;

        $this->place($match->next_match_id, $match->next_match_side, $winner && $loser ? $winner : null);
        $this->place($match->loser_next_match_id, $match->next_match_side, $winner && $loser ? $loser : null);
    }

    /**
     * Un partido de llave no se reabre si el siguiente (o el de tercer
     * puesto) ya se jugo: habria que deshacer ese resultado primero.
     */
    public function ensureCanReopen(GameMatch $match): void
    {
        if ($match->season_stage_id === null) {
            return;
        }

        $nextIds = array_filter([$match->next_match_id, $match->loser_next_match_id]);

        if ($nextIds !== [] && GameMatch::whereIn('id', $nextIds)->where('status', '!=', MatchStatus::Pending->value)->exists()) {
            throw ValidationException::withMessages(['reason' => self::REOPEN_BLOCKED]);
        }
    }

    /**
     * Orden de los casilleros de primera ronda para una llave de $size:
     * [1, 8, 4, 5, 2, 7, 3, 6] para 8. Cada par consecutivo es un cruce.
     *
     * @return list<int> numeros de cabeza de serie (desde 1)
     */
    public function seedOrder(int $size): array
    {
        $order = [1];

        for ($n = 2; $n <= $size; $n *= 2) {
            $next = [];

            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $n + 1 - $seed;
            }

            $order = $next;
        }

        return $order;
    }

    // ------------------------------------------------------------------

    /**
     * @param list<int> $seeds ids de season_team, en orden
     * @return Collection<int, GameMatch>
     */
    private function build(SeasonStage $stage, array $seeds): Collection
    {
        $count = count($seeds);
        $size = 2;
        $rounds = 1;

        while ($size < $count) {
            $size *= 2;
            $rounds++;
        }

        $order = $this->seedOrder($size);
        $created = collect();

        /** @var array<int, array<int, GameMatch|null>> $grid ronda => casillero => partido */
        $grid = [];

        // Partido por el tercer puesto: solo si hay dos semifinales reales
        // (con 3 participantes una "semifinal" es un bye y el tercero es
        // simplemente quien perdio la otra).
        $thirdPlace = null;
        if ($stage->has_third_place && $count >= 4) {
            $thirdPlace = $this->createMatch($stage, RoundType::ThirdPlace, $rounds, 1, 'Tercer puesto');
            $created->push($thirdPlace);
        }

        // De la final hacia atras, para que cada partido conozca a su siguiente.
        for ($round = $rounds; $round >= 1; $round--) {
            $slots = intdiv($size, 2 ** $round);

            for ($slot = 0; $slot < $slots; $slot++) {
                if ($round === 1) {
                    $home = $order[$slot * 2] <= $count ? $seeds[$order[$slot * 2] - 1] : null;
                    $away = $order[$slot * 2 + 1] <= $count ? $seeds[$order[$slot * 2 + 1] - 1] : null;

                    // Cruce con bye: sin partido; el participante pasa directo.
                    if ($rounds > 1 && ($home === null || $away === null)) {
                        $grid[1][$slot] = null;
                        $this->seat($grid[2][intdiv($slot, 2)], $this->sideOf($slot), $home ?? $away);

                        continue;
                    }
                }

                $match = $this->createMatch($stage, $this->roundType($round, $rounds), $round, $slot, $this->roundName($round, $rounds));

                if ($round < $rounds) {
                    $match->update([
                        'next_match_id' => $grid[$round + 1][intdiv($slot, 2)]->id,
                        'next_match_side' => $this->sideOf($slot)->value,
                        'loser_next_match_id' => $round === $rounds - 1 ? $thirdPlace?->id : null,
                    ]);
                }

                if ($round === 1) {
                    $this->seat($match, ParticipantSide::Home, $home);
                    $this->seat($match, ParticipantSide::Away, $away);
                }

                $grid[$round][$slot] = $match;
                $created->push($match);
            }
        }

        return $created;
    }

    private function createMatch(SeasonStage $stage, RoundType $type, int $round, int $slot, string $roundName): GameMatch
    {
        // matches.matchday_id es obligatorio: cada ronda usa su jornada.
        $matchday = Matchday::firstOrCreate([
            'season_id' => $stage->season_id,
            'name' => mb_substr(mb_substr($stage->name, 0, 50 - mb_strlen($roundName) - 3).' — '.$roundName, 0, 50),
        ]);

        return GameMatch::create([
            'season_id' => $stage->season_id,
            'matchday_id' => $matchday->id,
            'season_stage_id' => $stage->id,
            'discipline_id' => $stage->season->discipline_id,
            'round_type' => $type->value,
            'bracket_round' => $round,
            'bracket_slot' => $slot,
            'status' => MatchStatus::Pending->value,
        ]);
    }

    private function seat(GameMatch $match, ParticipantSide $side, int $seasonTeamId): void
    {
        EventParticipant::create([
            'match_id' => $match->id,
            'participant_type' => 'season_team',
            'participant_id' => $seasonTeamId,
            'side' => $side->value,
        ]);
    }

    /** Pone (o quita, con null) al participante en un lado del partido destino. */
    private function place(?int $matchId, ?string $side, ?EventParticipant $source): void
    {
        if ($matchId === null || $side === null) {
            return;
        }

        EventParticipant::where('match_id', $matchId)->where('side', $side)->delete();

        if ($source !== null) {
            EventParticipant::create([
                'match_id' => $matchId,
                'participant_type' => $source->participant_type,
                'participant_id' => $source->participant_id,
                'side' => $side,
            ]);
        }
    }

    private function sideOf(int $slot): ParticipantSide
    {
        return $slot % 2 === 0 ? ParticipantSide::Home : ParticipantSide::Away;
    }

    private function roundType(int $round, int $rounds): RoundType
    {
        return match ($rounds - $round) {
            0 => RoundType::Final,
            1 => RoundType::SemiFinal,
            2 => RoundType::QuarterFinal,
            default => RoundType::Regular,
        };
    }

    private function roundName(int $round, int $rounds): string
    {
        return match ($rounds - $round) {
            0 => 'Final',
            1 => 'Semifinal',
            2 => 'Cuartos de final',
            default => "Ronda {$round}",
        };
    }

    private function ensureManages(User $actor, SeasonStage $stage): void
    {
        if (! SeasonAccess::manages($actor, $stage->season)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador gestionan la llave de esta fase.');
        }
    }
}
