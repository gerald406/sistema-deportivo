<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\RoundType;
use App\Enums\SportFormatType;
use App\Models\EventParticipant;
use App\Models\GamesEdition;
use App\Models\GamesEditionMedal;
use App\Models\GameMatch;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Calculo del medallero por equipo de una edicion (games_edition_medals).
 * Dato derivado: se reconstruye completo. Se invoca de forma sincrona tras
 * guardar un resultado, por las mismas razones documentadas en
 * StandingService (misma transaccion, sin cola en XAMPP, costo bajo).
 *
 * Reglas confirmadas (regla 5A) + decisiones documentadas:
 *   - Individual / postas (formato efectivo): puestos 1/2/3 => oro/plata/
 *     bronce para el EQUIPO del atleta (o de la posta). Empates comparten
 *     medalla (dos primeros = dos oros). Solo cuentan pruebas de ronda
 *     "regular" o "final": las series, cuartos y semifinales son
 *     clasificatorias y no dan medalla.
 *   - Enfrentamientos: solo la ronda "final" da oro (ganador) y plata
 *     (perdedor); cada perdedor de "semifinal" recibe bronce. Una final o
 *     semifinal empatada no da medallas. Aplica a equipos y a partidas
 *     individuales (el jugador aporta a su equipo).
 */
class MedalService
{
    public function __construct(private GameMatchService $matches)
    {
    }

    public function recalculateEdition(GamesEdition $edition): void
    {
        $seasonIds = $edition->seasons()->pluck('id');
        $medals = []; // team_id => [gold, silver, bronze]

        $add = function (?int $teamId, int $slot) use (&$medals) {
            if ($teamId) {
                $medals[$teamId] ??= [0, 0, 0];
                $medals[$teamId][$slot]++;
            }
        };

        $load = fn ($q) => $q->with(['participants.participant' => fn (MorphTo $m) => $m->morphWith([
            SeasonTeamPlayer::class => ['seasonTeam:id,team_id'],
        ])]);

        // Individual y postas
        foreach ([SportFormatType::IndividualRanked, SportFormatType::TeamRelay] as $format) {
            $ranked = $this->matches->queryByFormat($format)
                ->whereIn('season_id', $seasonIds)
                ->where('status', MatchStatus::Played->value)
                ->whereIn('round_type', [RoundType::Regular->value, RoundType::Final->value])
                ->tap($load)->get();

            foreach ($ranked as $match) {
                foreach ($match->participants as $p) {
                    if (in_array($p->position, [1, 2, 3], true)) {
                        $add($this->teamOf($p), $p->position - 1);
                    }
                }
            }
        }

        // Enfrentamientos: final y semifinal
        $h2h = $this->matches->queryByFormat(SportFormatType::HeadToHead)
            ->whereIn('season_id', $seasonIds)
            ->whereIn('status', [MatchStatus::Played->value, MatchStatus::Walkover->value])
            ->whereIn('round_type', [RoundType::Final->value, RoundType::SemiFinal->value])
            ->tap($load)->get();

        foreach ($h2h as $match) {
            /** @var GameMatch $match */
            $winner = $match->participants->firstWhere('position', 1);
            $loser = $match->participants->firstWhere('position', 2);

            if (! $winner || ! $loser) {
                continue; // empate: sin medallas
            }

            if ($match->round_type === RoundType::Final) {
                $add($this->teamOf($winner), 0);
                $add($this->teamOf($loser), 1);
            } else {
                $add($this->teamOf($loser), 2);
            }
        }

        GamesEditionMedal::where('games_edition_id', $edition->id)->delete();

        foreach ($medals as $teamId => [$gold, $silver, $bronze]) {
            GamesEditionMedal::create([
                'games_edition_id' => $edition->id,
                'team_id' => $teamId,
                'gold' => $gold,
                'silver' => $silver,
                'bronze' => $bronze,
            ]);
        }
    }

    /** Medallero ordenado olimpicamente: oros, platas, bronces, nombre. */
    public function tableFor(GamesEdition $edition)
    {
        return GamesEditionMedal::with('team:id,name,logo_path')
            ->where('games_edition_id', $edition->id)
            ->get()
            ->sortBy([
                fn ($a, $b) => $b->gold <=> $a->gold,
                fn ($a, $b) => $b->silver <=> $a->silver,
                fn ($a, $b) => $b->bronze <=> $a->bronze,
                fn ($a, $b) => strcmp($a->team->name, $b->team->name),
            ])
            ->values();
    }

    private function teamOf(EventParticipant $p): ?int
    {
        $model = $p->participant;

        return match (true) {
            $model instanceof SeasonTeam => $model->team_id,
            $model instanceof SeasonTeamPlayer => $model->seasonTeam?->team_id,
            default => null,
        };
    }
}
