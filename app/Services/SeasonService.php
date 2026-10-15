<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Season;
use App\Models\GamesEdition;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
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
 *   - No se crean a mano (D19): salen del cuadro de puntaje
 *     (CompetitionGeneratorService).
 */
class SeasonService
{
    /** Lo unico que se edita de una competencia: el resto viene del cuadro de puntaje. */
    public const EDITABLE = ['name', 'start_date', 'end_date', 'status', 'is_active'];

    /**
     * Las competencias no se crean a mano: las crea
     * CompetitionGeneratorService desde el cuadro de puntaje. Aqui solo se
     * ajustan nombre, fechas, estado, activa y fases de puntaje; olimpiada,
     * deporte, prueba, nivel, genero y fila del cuadro se ignoran aunque
     * lleguen en $data (vienen del navegador).
     *
     * @param array{name: string, start_date: string|null, end_date: string|null, status: string, is_active: bool} $data
     * @param array<int, array{id?: int|string|null, phase_name: string, pts_win: int, pts_draw: int, pts_loss: int, pts_walkover: int}> $phases
     */
    public function update(Season $season, array $data, array $phases, User $actor): Season
    {
        $this->ensureCanUseEdition($season->games_edition_id, $actor);
        $this->ensureHasPhases($phases);

        $data = Arr::only($data, self::EDITABLE);

        return DB::transaction(function () use ($season, $data, $phases) {
            $season->update($data);
            $this->syncPhases($season, $phases);

            // Activar/desactivar la competencia cambia el puntaje general.
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
     * Olimpiadas cuyas competencias $actor puede editar: el admin en
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
        // Se revalida aqui y no solo en SeasonPolicy: cualquier metodo
        // publico del componente es invocable desde el navegador.
        if (! $this->editionsFor($actor)->contains('id', $gamesEditionId)) {
            throw new AuthorizationException('Solo puedes gestionar competencias de tus propias olimpiadas.');
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
