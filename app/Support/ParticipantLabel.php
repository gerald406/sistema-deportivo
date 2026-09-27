<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EventParticipant;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Nombre legible de un participante polimorfico (equipo inscrito o
 * jugador del plantel). Compartido por Partidos, Parciales, Resultados e
 * Incidencias.
 */
class ParticipantLabel
{
    public static function for(EventParticipant $p): string
    {
        $model = $p->participant;

        return match (true) {
            $model instanceof SeasonTeam => $model->team?->name ?? '¿?',
            $model instanceof SeasonTeamPlayer => $model->player ? "{$model->player->last_name}, {$model->player->first_name}" : '¿?',
            default => '(participante eliminado)',
        };
    }

    /** Eager load necesario para for() sin N+1: ->with(['participants' => ParticipantLabel::eagerLoad()]). */
    public static function eagerLoad(): \Closure
    {
        return fn ($q) => $q->orderByRaw("FIELD(side, 'home', 'away')")->orderBy('id')
            ->with(['participant' => fn (MorphTo $m) => $m->morphWith([
                SeasonTeam::class => ['team:id,name'],
                SeasonTeamPlayer::class => ['player:id,first_name,last_name', 'seasonTeam.team:id,name'],
            ])]);
    }
}
