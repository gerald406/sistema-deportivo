<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Season;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Season + sus fases de puntaje (ScoringConfig), que se editan como
 * sub-formulario del mismo modal y se guardan en una sola transaccion.
 *
 * Decisiones confirmadas (Fase 4):
 *   - Season no tiene sede: la sede se elige por partido (matches.venue_id).
 *   - Varias fases de puntaje por competencia, minimo una.
 *   - scoring_configs.rules (JSON) no se edita todavia: queda NULL hasta
 *     definir el calculo de SeasonStanding.
 *   - Una competencia con historial no se borra: se cierra o desactiva.
 */
class SeasonService
{
    /**
     * @param array{games_edition_id: int, sport_id: int, name: string, start_date: string|null, end_date: string|null, status: string, is_active: bool} $data
     * @param array<int, array{id?: int|string|null, phase_name: string, pts_win: int, pts_draw: int, pts_loss: int, pts_walkover: int}> $phases
     */
    public function register(array $data, array $phases, User $actor): Season
    {
        $this->ensureCanUseEdition((int) $data['games_edition_id'], $actor);
        $this->ensureEventBelongsToEdition($data);
        $this->ensureHasPhases($phases);

        return DB::transaction(function () use ($data, $phases) {
            $season = Season::create($data);
            $this->syncPhases($season, $phases);

            return $season;
        });
    }

    public function update(Season $season, array $data, array $phases, User $actor): Season
    {
        $this->ensureCanUseEdition((int) $data['games_edition_id'], $actor);
        $this->ensureEventBelongsToEdition($data);
        $this->ensureHasPhases($phases);

        // Cambiar el deporte de una competencia que ya tiene equipos o
        // partidos romperia la coherencia con disciplinas, posiciones y
        // tipos de evento (todos dependen del deporte).
        if ((int) $data['sport_id'] !== $season->sport_id
            && ($season->seasonTeams()->exists() || $season->matches()->exists())) {
            throw ValidationException::withMessages([
                'form.sport_id' => 'No se puede cambiar el deporte: la competencia ya tiene equipos inscritos o partidos.',
            ]);
        }

        return DB::transaction(function () use ($season, $data, $phases) {
            $season->update($data);
            $this->syncPhases($season, $phases);

            // Activar/desactivar la competencia o cambiar su fila del
            // cuadro cambia el puntaje general.
            app(AwardService::class)->rebuild($season->gamesEdition()->first());

            return $season;
        });
    }

    /**
     * Sin soft deletes y con cascadas en conflicto (ver migracion de
     * suspensions): solo se borra una competencia vacia. scoring_configs
     * se va por CASCADE.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(Season $season): bool|string
    {
        if ($season->seasonTeams()->exists()) {
            return 'No se puede eliminar: la competencia tiene equipos inscritos. Ciérrala o desactívala en su lugar.';
        }

        if ($season->matchdays()->exists() || $season->matches()->exists()) {
            return 'No se puede eliminar: la competencia tiene jornadas o partidos. Ciérrala o desactívala en su lugar.';
        }

        $edition = $season->gamesEdition()->first();

        try {
            $season->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la competencia tiene historial asociado. Ciérrala o desactívala en su lugar.';
        }

        // Sus fases y podio directo se fueron en CASCADE: el puntaje general se recalcula.
        app(AwardService::class)->rebuild($edition);

        return true;
    }

    /**
     * Olimpiadas en las que $actor puede crear/mover competencias: el admin en
     * cualquiera, el organizador solo en las suyas (misma regla que
     * SeasonPolicy::update, que mira gamesEdition.organizer_id).
     */
    public function editionsFor(User $actor): Collection
    {
        return GamesEdition::query()
            ->when(! $actor->hasRole('admin'), fn ($q) => $q->where('organizer_id', $actor->id))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }

    private function ensureCanUseEdition(int $gamesEditionId, User $actor): void
    {
        // SeasonPolicy::create no conoce la olimpiada destino: sin esto un
        // organizador podria crear (o mover) competencias en olimpiadas ajenas
        // mandando otro games_edition_id desde el navegador.
        if (! $this->editionsFor($actor)->contains('id', $gamesEditionId)) {
            throw new AuthorizationException('Solo puedes gestionar competencias de tus propias olimpiadas.');
        }
    }

    /** La fila del cuadro (opcional) debe ser de la misma olimpiada. */
    private function ensureEventBelongsToEdition(array $data): void
    {
        $eventId = $data['games_edition_event_id'] ?? null;

        if ($eventId !== null && ! GamesEditionEvent::whereKey($eventId)->where('games_edition_id', $data['games_edition_id'])->exists()) {
            throw ValidationException::withMessages([
                'form.games_edition_event_id' => 'La fila del cuadro no pertenece a esta olimpiada.',
            ]);
        }
    }

    private function ensureHasPhases(array $phases): void
    {
        if ($phases === []) {
            throw ValidationException::withMessages([
                'phases' => 'La competencia necesita al menos una fase de puntaje.',
            ]);
        }
    }

    /**
     * Upsert de las fases enviadas + borrado de las que se quitaron del
     * formulario. Los ids que llegan del navegador solo se aceptan si
     * pertenecen a ESTA competencia (si no, se tratan como fase nueva).
     * Nada referencia a scoring_configs, asi que borrar una fase es seguro.
     */
    private function syncPhases(Season $season, array $phases): void
    {
        $existingIds = $season->scoringConfigs()->pluck('id')->all();

        $toUpdate = [];
        $toCreate = [];

        foreach ($phases as $phase) {
            $values = [
                'phase_name' => trim($phase['phase_name']),
                'pts_win' => (int) $phase['pts_win'],
                'pts_draw' => (int) $phase['pts_draw'],
                'pts_loss' => (int) $phase['pts_loss'],
                'pts_walkover' => (int) $phase['pts_walkover'],
            ];

            $id = (int) ($phase['id'] ?? 0);

            if ($id && in_array($id, $existingIds, true)) {
                $toUpdate[$id] = $values;
            } else {
                $toCreate[] = $values;
            }
        }

        // Orden deliberado para no chocar con unique(season_id, phase_name)
        // cuando en un mismo guardado se renombra "A" -> "B" y se agrega
        // otra "A": 1) borrar las quitadas, 2) actualizar, 3) crear.
        $season->scoringConfigs()->whereNotIn('id', array_keys($toUpdate) ?: [0])->delete();

        foreach ($toUpdate as $id => $values) {
            $season->scoringConfigs()->whereKey($id)->update($values);
        }

        foreach ($toCreate as $values) {
            $season->scoringConfigs()->create($values);
        }
    }

    /**
     * Competencias que $actor ve en los reportes de solo lectura (/reportes),
     * union si tiene varios roles:
     *   - Admin: todas.
     *   - Organizador: las de sus olimpiadas.
     *   - Delegado: aquellas donde su equipo esta inscrito
     *     (SeasonTeamService::scopeVisibleTo(), delegado efectivo).
     *   - Cualquier otro: ninguna.
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('admin')) {
            return $query;
        }

        if (! $actor->hasAnyRole(['organizador', 'delegado'])) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn ($q) => $q
            ->when($actor->hasRole('organizador'), fn ($q) => $q->orWhereHas('gamesEdition', fn ($t) => $t->where('organizer_id', $actor->id)))
            ->orWhereHas('seasonTeams', fn ($st) => app(SeasonTeamService::class)->scopeVisibleTo($st, $actor)));
    }
}
