<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EditionTeamStatus;
use App\Enums\SeasonStatus;
use App\Enums\SeasonTeamPlayerStatus;
use App\Enums\SportFormatType;
use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Inscripcion de una delegacion en las disciplinas (competencias) de la
 * olimpiada (D22). CAMBIO DE REGLA respecto de la regla 4 de CLAUDE.md: el
 * DELEGADO (titular o suplente) inscribe y retira a SU delegacion, mientras
 * este abierta la inscripcion (games_editions.registration_deadline);
 * quien gestiona la olimpiada lo hace siempre. La inscripcion sigue siendo
 * season_team: la crea SeasonTeamService::ensureFor() (exige delegacion
 * inscrita en la olimpiada y no retirada ni descalificada).
 */
class DelegationEnrollmentService
{
    public const TEAM = 'team';

    public const INDIVIDUAL = 'individual';

    public function __construct(
        private GamesEditionService $editions,
        private SeasonTeamPlayerService $rosters,
        private SeasonTeamService $seasonTeams,
        private GameMatchService $matches,
    ) {
    }

    /** Olimpiadas en las que $actor inscribe delegaciones: las que gestiona y aquellas donde es delegado. */
    public function editionsFor(User $actor): Collection
    {
        return $this->editions->scopeManagedBy(GamesEdition::query(), $actor)->get(['id', 'name', 'is_active', 'start_date', 'registration_deadline'])
            ->merge($actor->hasRole('delegado') && $actor->can('players.manage')
                ? GamesEdition::whereHas('editionTeams', fn ($t) => $this->delegateOf($t, $actor))
                    ->get(['id', 'name', 'is_active', 'start_date', 'registration_deadline'])
                : collect())
            ->unique('id')
            ->sortByDesc(fn (GamesEdition $e) => [$e->is_active, $e->start_date?->timestamp])
            ->values();
    }

    /** Delegaciones habilitadas de la olimpiada que $actor inscribe: todas si la gestiona; si no, las suyas. */
    public function delegationsFor(User $actor, GamesEdition $edition): Collection
    {
        $manages = $this->editions->manages($actor, $edition);

        if (! $manages && ! ($actor->hasRole('delegado') && $actor->can('players.manage'))) {
            return collect();
        }

        return Team::query()
            ->whereHas('editionTeams', fn ($t) => $t
                ->where('games_edition_id', $edition->id)
                ->where('status', EditionTeamStatus::Registered->value)
                ->unless($manages, fn ($q) => $this->delegateOf($q, $actor)))
            ->orderBy('name')
            ->get(['id', 'name', 'director_name']);
    }

    /**
     * Disciplinas de la olimpiada para la delegacion: cada competencia
     * activa con su inscripcion (si existe), el plantel habilitado y los
     * topes de su fila del cuadro.
     *
     * @return Collection<int, object{season: Season, seasonTeam: ?SeasonTeam, participants: int, min: ?int, max: ?int, kind: string}>
     */
    public function board(GamesEdition $edition, Team $team): Collection
    {
        $seasons = $edition->seasons()
            ->where('is_active', true)
            ->with(['sport:id,name,format_type', 'discipline:id,name,format_type', 'category:id,name,min_age', 'event:id,min_players,max_players'])
            ->orderBy('name')
            ->get();

        $enrolled = SeasonTeam::where('team_id', $team->id)
            ->whereIn('season_id', $seasons->pluck('id'))
            ->withCount(['roster as participants_count' => fn ($q) => $q->where('status', '!=', SeasonTeamPlayerStatus::Inactive->value)])
            ->get()
            ->keyBy('season_id');

        return $seasons->map(fn (Season $season) => (object) [
            'season' => $season,
            'seasonTeam' => $enrolled->get($season->id),
            'participants' => (int) ($enrolled->get($season->id)?->participants_count ?? 0),
            'min' => $season->event?->min_players,
            'max' => $season->event?->max_players,
            'kind' => $this->kindOf($season),
        ]);
    }

    /**
     * Colectiva o individual, segun los datos de la competencia: posta
     * (team_relay), enfrentamiento con mas de un participante por
     * delegacion, o fila con minimo de varios participantes = colectiva.
     */
    public function kindOf(Season $season): string
    {
        $format = $this->matches->formatFor($season, $season->discipline);
        $max = $season->event?->max_players;

        $team = $format === SportFormatType::TeamRelay
            || ($format === SportFormatType::HeadToHead && $max !== 1)
            || ($season->event?->min_players ?? 0) > 1;

        return $team ? self::TEAM : self::INDIVIDUAL;
    }

    /** Puede inscribir o retirar a $team en $season ahora mismo (sin excepciones: para la vista). */
    public function canEnroll(User $actor, Season $season, Team $team): bool
    {
        try {
            $this->ensureCanEnroll($actor, $season, $team);

            return true;
        } catch (AuthorizationException|ValidationException) {
            return false;
        }
    }

    /**
     * Quien inscribe: los mismos que arman el plantel
     * (SeasonTeamPlayerService::canRegisterFor()); el delegado solo hasta
     * el cierre de inscripciones. Nadie en una competencia cerrada o
     * inactiva. Tambien la usa registerInCompetition() cuando el primer
     * participante crearia la inscripcion.
     */
    public function ensureCanEnroll(User $actor, Season $season, Team $team): void
    {
        $season->loadMissing('gamesEdition');

        if (! $this->rosters->canRegisterFor($actor, $season, $team)) {
            throw new AuthorizationException('Solo los delegados de la delegación o el organizador de la olimpiada la inscriben en las disciplinas.');
        }

        if ($season->status === SeasonStatus::Closed || ! $season->is_active) {
            throw ValidationException::withMessages(['enrollment' => 'La disciplina está cerrada o inactiva.']);
        }

        if (! $this->editions->manages($actor, $season->gamesEdition) && ! $season->gamesEdition->registrationOpen()) {
            throw ValidationException::withMessages([
                'enrollment' => 'Las inscripciones cerraron el '.$season->gamesEdition->registration_deadline->format('d/m/Y').': solo el organizador puede inscribir o retirar disciplinas.',
            ]);
        }
    }

    /**
     * Inscribe a la delegacion en la disciplina (idempotente). Una
     * inscripcion que la organizacion desactivo solo la reactiva quien
     * gestiona la olimpiada.
     */
    public function enroll(Season $season, Team $team, User $actor): SeasonTeam
    {
        $this->ensureCanEnroll($actor, $season, $team);

        $existing = SeasonTeam::where('season_id', $season->id)->where('team_id', $team->id)->first();

        if ($existing && ! $existing->is_active) {
            if (! $this->editions->manages($actor, $season->gamesEdition)) {
                throw ValidationException::withMessages(['enrollment' => 'La organización desactivó esta inscripción: consúltalo con el organizador.']);
            }

            $existing->update(['is_active' => true]);
        }

        return $existing ?? $this->seasonTeams->ensureFor($season, $team);
    }

    /**
     * Retira a la delegacion de la disciplina. Solo si su plantel esta
     * vacio (para no perder participantes sin querer) y sin partidos; el
     * resto lo decide SeasonTeamService::delete().
     *
     * Devuelve true o el mensaje exacto para el toast.
     */
    public function withdraw(SeasonTeam $seasonTeam, User $actor): bool|string
    {
        $seasonTeam->loadMissing('season.gamesEdition', 'team');
        $this->ensureCanEnroll($actor, $seasonTeam->season, $seasonTeam->team);

        if ($seasonTeam->roster()->exists()) {
            return 'No se puede retirar: la disciplina tiene participantes en su plantel. Quítalos primero en el plantel.';
        }

        return $this->seasonTeams->delete($seasonTeam);
    }

    private function delegateOf($query, User $actor)
    {
        return $query->where('status', EditionTeamStatus::Registered->value)
            ->where(fn ($d) => $d->where('delegate_id', $actor->id)->orWhere('substitute_delegate_id', $actor->id));
    }
}
