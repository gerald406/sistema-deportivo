<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\RoundType;
use App\Enums\SportFormatType;
use App\Models\EventParticipant;
use App\Models\Season;
use App\Models\SeasonStanding;
use App\Models\SeasonTeam;

/**
 * Calculo de la tabla de posiciones (season_standings). NO es un
 * formulario: la tabla es un dato derivado que se reconstruye completa.
 *
 * DECISION DE DISENO (Fase 7, modulo 15) — por que un metodo de Service
 * invocado de forma SINCRONA tras guardar un resultado, y no un Observer
 * ni un Job:
 *   - Lo invoca ResultService::afterResultChanged(), dentro de la MISMA
 *     transaccion que guarda el resultado (al cerrar y al reabrir). El
 *     resultado y la tabla se confirman juntos o no se confirma ninguno:
 *     nunca hay una tabla que contradiga los resultados.
 *   - Observer (GameMatch::updated): se dispararia en cualquier update
 *     del partido (reprogramar, cancelar, cambiar sede) y a mitad de la
 *     transaccion, antes de que ResultService guarde los puestos de los
 *     participantes; ademas esconde el flujo a quien lea el codigo.
 *   - Job en cola: QUEUE_CONNECTION=database requiere un `queue:work`
 *     corriendo, que en el entorno XAMPP no existe; la tabla quedaria
 *     desactualizada sin aviso.
 *   - Costo: reconstruir una temporada es O(partidos) con 2 consultas;
 *     con el volumen de un torneo es despreciable. Si en Fase 9 crece, se
 *     puede mover a un Job ShouldBeUnique por temporada sin tocar a los
 *     llamadores (solo este metodo).
 *
 * Reglas confirmadas (regla 2A/3A/4A):
 *   - Solo cuentan enfrentamientos (formato efectivo head_to_head) de
 *     ronda "regular", Jugados o con Walkover, con la PRIMERA fase de
 *     puntaje de la temporada (scoring_configs de menor id).
 *   - Solo enfrentamientos entre EQUIPOS (season_team): las partidas
 *     individuales (ej. ajedrez en modo jugadores) no suman a una tabla
 *     de equipos. Individual/postas alimentan el medallero, no la tabla.
 *   - Walkover: el presente suma pts_win y una victoria; el ausente suma
 *     pts_walkover (ej. -1), una derrota y un walkover en contra.
 *   - extra_stats: {scored, conceded, diff} para desempates (goles o
 *     sets segun el deporte).
 */
class StandingService
{
    public function __construct(private GameMatchService $matches)
    {
    }

    public function recalculateSeason(Season $season): void
    {
        $scoring = $season->scoringConfigs()->orderBy('id')->first();
        $pts = [
            'win' => $scoring->pts_win ?? 3,
            'draw' => $scoring->pts_draw ?? 1,
            'loss' => $scoring->pts_loss ?? 0,
            'walkover' => $scoring->pts_walkover ?? -1,
        ];

        $rows = SeasonTeam::where('season_id', $season->id)->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => ['matches_played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
                'walkovers_against' => 0, 'points' => 0, 'scored' => 0, 'conceded' => 0]])
            ->all();

        $matches = $this->matches->queryByFormat(SportFormatType::HeadToHead)
            ->where('season_id', $season->id)
            ->where('round_type', RoundType::Regular->value)
            ->whereIn('status', [MatchStatus::Played->value, MatchStatus::Walkover->value])
            ->with(['participants' => fn ($q) => $q->where('participant_type', 'season_team')])
            ->get();

        foreach ($matches as $match) {
            if ($match->participants->count() !== 2) {
                continue; // partida individual o datos incompletos
            }

            [$a, $b] = [$match->participants[0], $match->participants[1]];

            foreach ([[$a, $b], [$b, $a]] as [$me, $rival]) {
                /** @var EventParticipant $me */
                if (! isset($rows[$me->participant_id])) {
                    continue;
                }

                $r = &$rows[$me->participant_id];
                $r['matches_played']++;

                if ($match->status === MatchStatus::Walkover) {
                    if ($me->position === 1) {
                        $r['wins']++;
                        $r['points'] += $pts['win'];
                    } else {
                        $r['losses']++;
                        $r['walkovers_against']++;
                        $r['points'] += $pts['walkover'];
                    }
                } else {
                    $r['scored'] += (int) $me->result_value;
                    $r['conceded'] += (int) $rival->result_value;

                    if ($me->position === 1 && $rival->position === 1) {
                        $r['draws']++;
                        $r['points'] += $pts['draw'];
                    } elseif ($me->position === 1) {
                        $r['wins']++;
                        $r['points'] += $pts['win'];
                    } else {
                        $r['losses']++;
                        $r['points'] += $pts['loss'];
                    }
                }

                unset($r);
            }
        }

        foreach ($rows as $seasonTeamId => $r) {
            SeasonStanding::updateOrCreate(
                ['season_id' => $season->id, 'season_team_id' => $seasonTeamId],
                [
                    'matches_played' => $r['matches_played'], 'wins' => $r['wins'], 'draws' => $r['draws'],
                    'losses' => $r['losses'], 'walkovers_against' => $r['walkovers_against'], 'points' => $r['points'],
                    'extra_stats' => ['scored' => $r['scored'], 'conceded' => $r['conceded'], 'diff' => $r['scored'] - $r['conceded']],
                ],
            );
        }

        // Filas de inscripciones que ya no existen (se van en CASCADE, pero
        // por si acaso) o de temporadas sin enfrentamientos por equipos.
        SeasonStanding::where('season_id', $season->id)->whereNotIn('season_team_id', array_keys($rows) ?: [0])->delete();
    }

    /**
     * Tabla ordenada para mostrar: puntos, diferencia, anotados, nombre.
     */
    public function tableFor(Season $season)
    {
        return SeasonStanding::with('seasonTeam.team:id,name,logo_path')
            ->where('season_id', $season->id)
            ->get()
            ->sortBy([
                fn ($a, $b) => $b->points <=> $a->points,
                fn ($a, $b) => ($b->extra_stats['diff'] ?? 0) <=> ($a->extra_stats['diff'] ?? 0),
                fn ($a, $b) => ($b->extra_stats['scored'] ?? 0) <=> ($a->extra_stats['scored'] ?? 0),
                fn ($a, $b) => strcmp($a->seasonTeam->team->name, $b->seasonTeam->team->name),
            ])
            ->values();
    }
}
