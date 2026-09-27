<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\SeasonStatus;
use App\Models\GameMatch;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Suspension;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Support\ParticipantLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Metricas del panel de control, SIEMPRE acotadas a lo que el usuario
 * gestiona:
 *   - admin: todo.
 *   - organizador: sus torneos (tournaments.organizer_id).
 *   - delegado: sus equipos, segun el delegado EFECTIVO de cada
 *     inscripcion (SeasonTeam::effectiveDelegate(), regla 5 de CLAUDE.md).
 * Un usuario con varios roles ve la union de sus ambitos.
 */
class DashboardService
{
    /** @return array<string, int|null> null = el usuario no ve esa metrica */
    public function metrics(User $user): array
    {
        $manages = $user->can('matches.manage');

        return [
            'activeTournaments' => $user->can('tournaments.manage')
                ? Tournament::where('is_active', true)->when(! $user->hasRole('admin'), fn ($q) => $q->where('organizer_id', $user->id))->count()
                : null,
            'activeSeasons' => $this->seasonScope(Season::query(), $user)->where('status', SeasonStatus::Active->value)->count(),
            'todayMatches' => $this->matchScope(GameMatch::query(), $user)
                ->whereDate('scheduled_at', Carbon::today())->where('status', '!=', MatchStatus::Canceled->value)->count(),
            'overdueResults' => $manages
                ? $this->matchScope(GameMatch::query(), $user)->where('status', MatchStatus::Pending->value)->where('scheduled_at', '<', now())->count()
                : null,
            'pendingSuspensions' => $this->suspensionScope(Suspension::query(), $user)->where('is_served', false)->count(),
            'activeTeams' => $user->can('teams.manage')
                ? Team::where('is_active', true)->when(! $user->hasRole('admin'), fn ($q) => $q->where('delegate_id', $user->id))->count()
                : null,
        ];
    }

    /** Proximos partidos (hoy y 7 dias), no cancelados. */
    public function upcomingMatches(User $user, int $limit = 8): Collection
    {
        return $this->matchScope(GameMatch::query(), $user)
            ->where('status', MatchStatus::Pending->value)
            ->whereBetween('scheduled_at', [Carbon::today(), Carbon::today()->addDays(7)->endOfDay()])
            ->with($this->matchRelations())
            ->orderBy('scheduled_at')->limit($limit)->get();
    }

    /** Ultimos resultados cargados. */
    public function latestResults(User $user, int $limit = 5): Collection
    {
        return $this->matchScope(GameMatch::query(), $user)
            ->whereIn('status', [MatchStatus::Played->value, MatchStatus::Walkover->value])
            ->with($this->matchRelations())
            ->orderByDesc('updated_at')->limit($limit)->get();
    }

    // ------------------------------------------------------------------

    private function matchRelations(): array
    {
        return [
            'season:id,name,sport_id', 'season.sport:id,name,format_type', 'discipline:id,name,format_type',
            'matchday:id,name', 'venue:id,name', 'participants' => ParticipantLabel::eagerLoad(),
        ];
    }

    /** Inscripciones de las que $user es delegado efectivo. */
    private function effectiveDelegateSeasonTeams(User $user): Builder
    {
        return SeasonTeam::query()->select('season_team.id')->where(fn ($q) => $q
            ->where('delegate_id', $user->id)
            ->orWhere(fn ($q) => $q->whereNull('delegate_id')->whereHas('team', fn ($t) => $t->where('delegate_id', $user->id))));
    }

    private function seasonScope(Builder $q, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $q;
        }

        return $q->where(fn ($q) => $q->whereRaw('1 = 0')
            ->when($user->hasRole('organizador'), fn ($q) => $q->orWhereHas('tournament', fn ($t) => $t->where('organizer_id', $user->id)))
            ->when($user->hasRole('delegado'), fn ($q) => $q->orWhereHas('seasonTeams', fn ($st) => $st->whereIn('season_team.id', $this->effectiveDelegateSeasonTeams($user)))));
    }

    private function matchScope(Builder $q, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $q;
        }

        $mine = $this->effectiveDelegateSeasonTeams($user);

        return $q->where(fn ($q) => $q->whereRaw('1 = 0')
            ->when($user->hasRole('organizador'), fn ($q) => $q->orWhereHas('season.tournament', fn ($t) => $t->where('organizer_id', $user->id)))
            ->when($user->hasRole('delegado'), fn ($q) => $q->orWhereHas('participants', fn ($p) => $p->where(fn ($p) => $p
                ->where(fn ($p) => $p->where('participant_type', 'season_team')->whereIn('participant_id', $mine))
                ->orWhere(fn ($p) => $p->where('participant_type', 'season_team_player')
                    ->whereIn('participant_id', SeasonTeamPlayer::select('id')->whereIn('season_team_id', $mine)))))));
    }

    private function suspensionScope(Builder $q, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $q;
        }

        return $q->where(fn ($q) => $q->whereRaw('1 = 0')
            ->when($user->hasRole('organizador'), fn ($q) => $q->orWhereHas('season.tournament', fn ($t) => $t->where('organizer_id', $user->id)))
            ->when($user->hasRole('delegado'), fn ($q) => $q->orWhereHas('seasonTeamPlayer', fn ($e) => $e->whereIn('season_team_id', $this->effectiveDelegateSeasonTeams($user)))));
    }
}
