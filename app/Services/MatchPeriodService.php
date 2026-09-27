<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchModality;
use App\Enums\MatchStatus;
use App\Enums\SportFormatType;
use App\Models\GameMatch;
use App\Models\User;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parciales de un enfrentamiento (match_periods): sets en voley, tiempos
 * en futbol, etc. Solo tienen sentido en formato head_to_head (la tabla
 * guarda puntos de local y visitante).
 *
 * Reglas: periodos 1..N consecutivos; N <= limite de la modalidad (al
 * mejor de 3 => 3, al mejor de 5 => 5; sin modalidad => MAX_PERIODS);
 * puntos >= 0; solo con el partido Programado: al cargar el resultado
 * final (modulo de resultados) quedan congelados.
 */
class MatchPeriodService
{
    public const MAX_PERIODS = 10;

    public function __construct(private GameMatchService $matches)
    {
    }

    /**
     * Reemplaza los parciales del partido. $periods en orden: [0] es el
     * periodo 1.
     *
     * @param array<int, array{home: int, away: int}> $periods
     */
    public function syncPeriods(GameMatch $match, array $periods, User $actor): void
    {
        $this->ensureEditable($match, $actor);

        $limit = $this->maxPeriods($match);

        if ($periods === []) {
            throw ValidationException::withMessages(['periods' => 'Indica al menos un periodo.']);
        }

        if (count($periods) > $limit) {
            throw ValidationException::withMessages(['periods' => "Este partido admite como máximo {$limit} periodos."]);
        }

        foreach ($periods as $i => $p) {
            foreach (['home', 'away'] as $side) {
                if (! is_numeric($p[$side] ?? null) || (int) $p[$side] < 0 || (int) $p[$side] != $p[$side]) {
                    throw ValidationException::withMessages(['periods' => 'Periodo ' . ($i + 1) . ': los puntos deben ser enteros de 0 o más.']);
                }
            }
        }

        DB::transaction(function () use ($match, $periods) {
            $match->periods()->delete();

            foreach (array_values($periods) as $i => $p) {
                $match->periods()->create([
                    'period_number' => $i + 1,
                    'home_points' => (int) $p['home'],
                    'away_points' => (int) $p['away'],
                ]);
            }
        });
    }

    /**
     * Borra todos los parciales. Devuelve true o el mensaje de por que no.
     */
    public function clear(GameMatch $match, User $actor): bool|string
    {
        try {
            $this->ensureEditable($match, $actor);
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->first();
        }

        $match->periods()->delete();

        return true;
    }

    public function maxPeriods(GameMatch $match): int
    {
        return match ($match->modality) {
            MatchModality::BestOf1 => 1,
            MatchModality::BestOf3 => 3,
            MatchModality::BestOf5 => 5,
            default => self::MAX_PERIODS,
        };
    }

    /** Suma de parciales por lado: ['home' => x, 'away' => y]. */
    public function totals(GameMatch $match): array
    {
        return [
            'home' => (int) $match->periods()->sum('home_points'),
            'away' => (int) $match->periods()->sum('away_points'),
        ];
    }

    private function ensureEditable(GameMatch $match, User $actor): void
    {
        $match->load('season.tournament', 'season.sport', 'discipline');

        if (! SeasonAccess::manages($actor, $match->season)) {
            throw new AuthorizationException('Solo el organizador del torneo carga los parciales de este partido.');
        }

        if ($this->matches->formatFor($match->season, $match->discipline) !== SportFormatType::HeadToHead) {
            throw ValidationException::withMessages(['periods' => 'Los parciales solo aplican a enfrentamientos (local vs. visitante).']);
        }

        if ($match->status !== MatchStatus::Pending) {
            throw ValidationException::withMessages(['periods' => 'El partido ya no está programado: sus parciales no se modifican.']);
        }
    }
}
