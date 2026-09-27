<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\User;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Jornadas (fechas) de una temporada. Reglas:
 *   - Solo en temporadas que $actor gestiona (SeasonAccess) y no Cerradas.
 *   - Las fechas de la jornada deben caer dentro de las de la temporada
 *     (cuando la temporada las tiene).
 *   - No se marca como completada si le quedan partidos pendientes.
 */
class MatchdayService
{
    /**
     * @param array{season_id: int, name: string, start_date: string|null, end_date: string|null, is_completed: bool, observations: string|null} $data
     */
    public function register(array $data, User $actor): Matchday
    {
        $season = Season::with('tournament')->findOrFail($data['season_id']);
        $this->ensureCanSchedule($season, $actor);
        $this->ensureDatesWithinSeason($season, $data);

        if (! empty($data['is_completed'])) {
            // Una jornada nueva no tiene partidos: completarla no tiene sentido.
            $data['is_completed'] = false;
        }

        return Matchday::create($data);
    }

    /**
     * La temporada de una jornada no se cambia (sus partidos quedarian en
     * otra temporada que la de matches.season_id).
     */
    public function update(Matchday $matchday, array $data, User $actor): Matchday
    {
        $matchday->load('season.tournament');
        $this->ensureCanSchedule($matchday->season, $actor);
        $this->ensureDatesWithinSeason($matchday->season, $data);

        if (! empty($data['is_completed']) && ! $matchday->is_completed) {
            $pending = $matchday->matches()->where('status', MatchStatus::Pending->value)->count();

            if ($pending > 0) {
                throw ValidationException::withMessages([
                    'form.is_completed' => "No se puede completar: la jornada tiene {$pending} partido(s) pendiente(s).",
                ]);
            }
        }

        unset($data['season_id']);
        $matchday->update($data);

        return $matchday;
    }

    /**
     * matches.matchday_id es CASCADE: borrar una jornada con partidos los
     * borraria (con sus resultados). Solo se borran jornadas vacias.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(Matchday $matchday): bool|string
    {
        if ($matchday->matches()->exists()) {
            return 'No se puede eliminar: la jornada tiene partidos programados. Elimina o reprograma los partidos primero.';
        }

        try {
            $matchday->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la jornada tiene historial asociado.';
        }

        return true;
    }

    private function ensureCanSchedule(Season $season, User $actor): void
    {
        if (! SeasonAccess::manages($actor, $season)) {
            throw new AuthorizationException('Solo el organizador del torneo gestiona las jornadas de esta temporada.');
        }

        if ($season->status === SeasonStatus::Closed) {
            throw ValidationException::withMessages(['form.season_id' => 'La temporada está cerrada: no admite cambios en sus jornadas.']);
        }
    }

    private function ensureDatesWithinSeason(Season $season, array $data): void
    {
        foreach (['start_date' => 'inicio', 'end_date' => 'fin'] as $field => $label) {
            if (empty($data[$field])) {
                continue;
            }

            $date = Carbon::parse($data[$field]);

            if (($season->start_date && $date->lt($season->start_date)) || ($season->end_date && $date->gt($season->end_date))) {
                $range = ($season->start_date?->format('d/m/Y') ?? '…') . ' - ' . ($season->end_date?->format('d/m/Y') ?? '…');
                throw ValidationException::withMessages([
                    "form.$field" => "La fecha de {$label} está fuera de la temporada ({$range}).",
                ]);
            }
        }
    }
}
