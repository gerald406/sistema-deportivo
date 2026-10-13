<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EditionTeamStatus;
use App\Enums\StageMode;
use App\Models\GamesEditionTeam;
use App\Models\SeasonStage;
use App\Models\SeasonStageResult;
use App\Models\SeasonTeamPlayer;
use App\Models\User;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Podio directo de una fase (D13): en lanzamientos, salto, sapo, ciclismo,
 * danza, estudiantina y puntualidad el sistema no lleva marcas ni partidos;
 * el organizador solo registra 1.o, 2.o y 3.o.
 *
 * Guardar REEMPLAZA el podio completo de la fase (enviar los tres puestos
 * vacios lo borra). Un puesto admite varias filas: puesto compartido.
 */
class DirectPodiumService
{
    public const PLACES = [1, 2, 3];

    /**
     * @param array<int, array<int|string, mixed>> $places puesto => una fila ['team_id' => int, 'season_team_player_id' => ?int]
     *                                                     o una lista de filas (puesto compartido)
     */
    public function save(SeasonStage $stage, array $places, User $actor): void
    {
        $stage->loadMissing('season.gamesEdition');
        $season = $stage->season;

        if (! SeasonAccess::manages($actor, $season)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador registran el podio.');
        }

        if ($stage->mode !== StageMode::DirectPodium) {
            throw ValidationException::withMessages(['podium' => 'Esta fase se juega con partidos: su podio sale de los resultados.']);
        }

        $rows = $this->normalize($places);
        $this->validate($stage, $rows);

        DB::transaction(function () use ($stage, $rows, $actor) {
            $stage->results()->delete();

            foreach ($rows as $row) {
                SeasonStageResult::create($row + ['season_stage_id' => $stage->id, 'recorded_by' => $actor->id]);
            }

            // El podio alimenta el puntaje general.
            app(AwardService::class)->rebuild($stage->season->gamesEdition);
        });
    }

    /**
     * @return list<array{place: int, team_id: int, season_team_player_id: ?int}>
     */
    private function normalize(array $places): array
    {
        $rows = [];

        foreach ($places as $place => $entries) {
            if (! in_array((int) $place, self::PLACES, true)) {
                throw ValidationException::withMessages(['podium' => 'El podio solo tiene 1.º, 2.º y 3.º puesto.']);
            }

            // Una sola fila (['team_id' => ...]) o una lista de filas.
            $entries = is_array($entries) && array_key_exists('team_id', $entries) ? [$entries] : (array) $entries;

            foreach ($entries as $entry) {
                $teamId = $entry['team_id'] ?? null;

                if ($teamId === null || $teamId === '') {
                    continue; // fila sin delegacion: se ignora
                }

                $playerId = $entry['season_team_player_id'] ?? null;

                $rows[] = [
                    'place' => (int) $place,
                    'team_id' => (int) $teamId,
                    'season_team_player_id' => $playerId === null || $playerId === '' ? null : (int) $playerId,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<array{place: int, team_id: int, season_team_player_id: ?int}> $rows
     */
    private function validate(SeasonStage $stage, array $rows): void
    {
        $season = $stage->season;
        $seen = [];

        $registered = GamesEditionTeam::where('games_edition_id', $season->games_edition_id)
            ->where('status', EditionTeamStatus::Registered->value)
            ->pluck('team_id')
            ->all();

        foreach ($rows as $row) {
            $key = "podium.{$row['place']}";

            if (! in_array($row['team_id'], $registered, true)) {
                throw ValidationException::withMessages([$key => 'La delegación no está inscrita en la olimpiada, o está retirada o descalificada.']);
            }

            // La misma delegacion puede subir dos veces al podio solo con
            // participantes distintos (pruebas individuales).
            $identity = 'who:'.$row['team_id'].':'.($row['season_team_player_id'] ?? 'team');
            $slot = 'slot:'.$row['team_id'].':'.$row['place'];

            if (isset($seen[$identity]) || isset($seen[$slot])) {
                throw ValidationException::withMessages([$key => 'La misma delegación o participante figura dos veces en el podio.']);
            }

            $seen[$identity] = true;
            $seen[$slot] = true;

            if ($row['season_team_player_id'] !== null && ! SeasonTeamPlayer::whereKey($row['season_team_player_id'])
                ->whereHas('seasonTeam', fn ($q) => $q->where('season_id', $season->id)->where('team_id', $row['team_id']))
                ->exists()) {
                throw ValidationException::withMessages([$key => 'El participante no está en el plantel de esa delegación en esta competencia.']);
            }
        }
    }
}
