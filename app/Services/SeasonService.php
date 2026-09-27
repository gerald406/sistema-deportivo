<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Season;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Season + sus fases de puntaje (ScoringConfig), que se editan como
 * sub-formulario del mismo modal y se guardan en una sola transaccion.
 *
 * Decisiones confirmadas (Fase 4):
 *   - Season no tiene sede: la sede se elige por partido (matches.venue_id).
 *   - Varias fases de puntaje por temporada, minimo una.
 *   - scoring_configs.rules (JSON) no se edita todavia: queda NULL hasta
 *     definir el calculo de SeasonStanding.
 *   - Una temporada con historial no se borra: se cierra o desactiva.
 */
class SeasonService
{
    /**
     * @param array{tournament_id: int, sport_id: int, games_edition_id: int|null, name: string, start_date: string|null, end_date: string|null, status: string, is_active: bool} $data
     * @param array<int, array{id?: int|string|null, phase_name: string, pts_win: int, pts_draw: int, pts_loss: int, pts_walkover: int}> $phases
     */
    public function register(array $data, array $phases, User $actor): Season
    {
        $this->ensureCanUseTournament((int) $data['tournament_id'], $actor);
        $this->ensureHasPhases($phases);

        return DB::transaction(function () use ($data, $phases) {
            $season = Season::create($data);
            $this->syncPhases($season, $phases);

            return $season;
        });
    }

    public function update(Season $season, array $data, array $phases, User $actor): Season
    {
        $this->ensureCanUseTournament((int) $data['tournament_id'], $actor);
        $this->ensureHasPhases($phases);

        // Cambiar el deporte de una temporada que ya tiene equipos o
        // partidos romperia la coherencia con disciplinas, posiciones y
        // tipos de evento (todos dependen del deporte).
        if ((int) $data['sport_id'] !== $season->sport_id
            && ($season->seasonTeams()->exists() || $season->matches()->exists())) {
            throw ValidationException::withMessages([
                'form.sport_id' => 'No se puede cambiar el deporte: la temporada ya tiene equipos inscritos o partidos.',
            ]);
        }

        return DB::transaction(function () use ($season, $data, $phases) {
            $season->update($data);
            $this->syncPhases($season, $phases);

            return $season;
        });
    }

    /**
     * Sin soft deletes y con cascadas en conflicto (ver migracion de
     * suspensions): solo se borra una temporada vacia. scoring_configs
     * se va por CASCADE.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(Season $season): bool|string
    {
        if ($season->seasonTeams()->exists()) {
            return 'No se puede eliminar: la temporada tiene equipos inscritos. Ciérrala o desactívala en su lugar.';
        }

        if ($season->matchdays()->exists() || $season->matches()->exists()) {
            return 'No se puede eliminar: la temporada tiene jornadas o partidos. Ciérrala o desactívala en su lugar.';
        }

        try {
            $season->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la temporada tiene historial asociado. Ciérrala o desactívala en su lugar.';
        }

        return true;
    }

    /**
     * Torneos en los que $actor puede crear/mover temporadas: el admin en
     * cualquiera, el organizador solo en los suyos (misma regla que
     * SeasonPolicy::update, que mira tournament.organizer_id).
     */
    public function tournamentsFor(User $actor)
    {
        return Tournament::query()
            ->when(! $actor->hasRole('admin'), fn ($q) => $q->where('organizer_id', $actor->id))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }

    private function ensureCanUseTournament(int $tournamentId, User $actor): void
    {
        // SeasonPolicy::create no conoce el torneo destino: sin esto un
        // organizador podria crear (o mover) temporadas en torneos ajenos
        // mandando otro tournament_id desde el navegador.
        if (! $this->tournamentsFor($actor)->contains('id', $tournamentId)) {
            throw new AuthorizationException('Solo puedes gestionar temporadas de tus propios torneos.');
        }
    }

    private function ensureHasPhases(array $phases): void
    {
        if ($phases === []) {
            throw ValidationException::withMessages([
                'phases' => 'La temporada necesita al menos una fase de puntaje.',
            ]);
        }
    }

    /**
     * Upsert de las fases enviadas + borrado de las que se quitaron del
     * formulario. Los ids que llegan del navegador solo se aceptan si
     * pertenecen a ESTA temporada (si no, se tratan como fase nueva).
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
}
