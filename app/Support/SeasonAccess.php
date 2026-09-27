<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Regla compartida por Jornadas, Partidos y Resultados (permiso
 * matches.manage): el admin gestiona cualquier temporada; el organizador
 * solo las de SUS torneos (tournaments.organizer_id).
 */
class SeasonAccess
{
    public static function manages(User $user, Season $season): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('organizador')
            && $user->can('matches.manage')
            && $season->tournament->organizer_id === $user->id;
    }

    /**
     * Temporadas que $user puede programar. Por defecto excluye las
     * Cerradas (no admiten jornadas ni partidos nuevos).
     */
    public static function manageableSeasons(User $user, bool $includeClosed = false): Collection
    {
        return Season::query()
            ->with(['tournament:id,name,organizer_id', 'sport:id,name,format_type'])
            ->unless($includeClosed, fn ($q) => $q->where('status', '!=', SeasonStatus::Closed->value))
            ->unless($user->hasRole('admin'), fn ($q) => $q->whereHas('tournament', fn ($t) => $t->where('organizer_id', $user->id)))
            ->orderByDesc('start_date')
            ->get();
    }
}
