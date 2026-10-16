<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EditionTeamStatus;
use App\Models\GamesEdition;
use App\Models\GamesEditionTeam;
use App\Models\SeasonStage;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Inscripcion de una delegacion en una olimpiada: serie, delegado titular
 * y suplente, estado y pago registrado. Reglas (docs/fases/00-especificacion.md):
 *   - Solo la gestiona quien gestiona la olimpiada (D2).
 *   - Una delegacion se inscribe una sola vez por olimpiada (D5), y el
 *     organizador solo inscribe las que estan a su cargo.
 *   - Titular y suplente son usuarios activos con rol delegado, distintos
 *     entre si, con los mismos permisos (D9, Art. 18 de las bases).
 *   - Con planteles ya armados no se borra: se retira o descalifica
 *     (Art. 22) cambiando el estado.
 */
class EditionTeamService
{
    public function __construct(private GamesEditionService $editions)
    {
    }

    /**
     * @param array{team_id: int, group_id: int|null, delegate_id: int|null, substitute_delegate_id: int|null, status?: string, fee_paid?: bool, observations?: string|null} $data
     */
    public function register(GamesEdition $edition, array $data, User $actor): GamesEditionTeam
    {
        $this->ensureManages($actor, $edition);

        $team = Team::find((int) ($data['team_id'] ?? 0));

        if (! $team) {
            throw ValidationException::withMessages(['form.team_id' => 'Selecciona una delegación.']);
        }

        // El id llega del navegador: un organizador solo inscribe las
        // delegaciones a su cargo (las que creo o ya gestiona), no cualquier
        // delegacion del catalogo.
        if (! $this->assignableTeams($actor)->whereKey($team->id)->exists()) {
            throw ValidationException::withMessages(['form.team_id' => 'No puedes inscribir esta delegación: no está a tu cargo o está inactiva.']);
        }

        if ($edition->editionTeams()->where('team_id', $team->id)->exists()) {
            throw ValidationException::withMessages(['form.team_id' => 'La delegación ya está inscrita en esta olimpiada.']);
        }

        return $edition->editionTeams()->create(['team_id' => $team->id] + $this->validated($edition, $data));
    }

    /**
     * La delegacion no se cambia (seria otra inscripcion): solo serie,
     * delegados, estado, pago y observaciones.
     *
     * @param array{group_id: int|null, delegate_id: int|null, substitute_delegate_id: int|null, status?: string, fee_paid?: bool, observations?: string|null} $data
     */
    public function update(GamesEditionTeam $entry, array $data, User $actor): GamesEditionTeam
    {
        $this->ensureManages($actor, $entry->edition);

        $entry->update($this->validated($entry->edition, $data));

        return $entry;
    }

    /**
     * Fases de la serie actual de la delegacion donde ya esta sentada en la
     * llave o en el podio (D28). Cambiarla de serie no las rehace: sirve
     * para avisar antes de moverla, no bloquea.
     *
     * @return Collection<int, string> "Competencia — Fase"
     */
    public function stagesUsingGroup(GamesEditionTeam $entry): Collection
    {
        if ($entry->group_id === null) {
            return collect();
        }

        $seasonTeamIds = SeasonTeam::where('team_id', $entry->team_id)
            ->whereHas('season', fn ($q) => $q->where('games_edition_id', $entry->games_edition_id))
            ->pluck('id');

        return SeasonStage::query()
            ->where('group_id', $entry->group_id)
            ->where(fn ($q) => $q
                ->whereHas('matches.participants', fn ($p) => $p->where('participant_type', 'season_team')->whereIn('participant_id', $seasonTeamIds))
                ->orWhereHas('results', fn ($r) => $r->where('team_id', $entry->team_id)))
            ->with('season:id,name')
            ->orderBy('season_id')
            ->get()
            ->map(fn (SeasonStage $stage) => "{$stage->season->name} — {$stage->name}")
            ->values();
    }

    /**
     * Una delegacion con planteles en competencias de esta olimpiada no se
     * borra (se perderia a quien responde por esos planteles): se cambia
     * su estado. Devuelve true o el mensaje exacto para el toast.
     */
    public function delete(GamesEditionTeam $entry, User $actor): bool|string
    {
        $this->ensureManages($actor, $entry->edition);

        $hasRosters = SeasonTeam::where('team_id', $entry->team_id)
            ->whereHas('season', fn ($q) => $q->where('games_edition_id', $entry->games_edition_id))
            ->exists();

        if ($hasRosters) {
            return 'No se puede eliminar: la delegación ya participa en competencias de esta olimpiada. Cámbiale el estado a "Retirada" o "Descalificada".';
        }

        try {
            $entry->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la delegación tiene historial asociado.';
        }

        return true;
    }

    /**
     * Delegaciones activas que $actor puede inscribir: el admin cualquiera;
     * el organizador las que estan a su cargo
     * (TeamRegistrationService::scopeManagedBy()).
     */
    public function assignableTeams(User $actor): Builder
    {
        return app(TeamRegistrationService::class)
            ->scopeManagedBy(Team::query(), $actor)
            ->where('teams.is_active', true);
    }

    /** Usuarios activos con rol delegado, para los selectores de titular y suplente. */
    public function delegateOptions(): Collection
    {
        return User::role('delegado')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /**
     * Delegaciones (teams) donde $actor es delegado titular o suplente en
     * alguna olimpiada.
     */
    public function scopeDelegatedTo(Builder $teams, User $actor): Builder
    {
        return $teams->whereIn('teams.id', GamesEditionTeam::query()
            ->where(fn ($q) => $q->where('delegate_id', $actor->id)->orWhere('substitute_delegate_id', $actor->id))
            ->select('team_id'));
    }

    private function ensureManages(User $actor, GamesEdition $edition): void
    {
        if (! $this->editions->manages($actor, $edition)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador gestionan sus delegaciones.');
        }
    }

    /**
     * Valida y normaliza los campos comunes a alta y edicion. Todo id llega
     * del navegador: la serie debe ser de ESTA olimpiada y los delegados,
     * usuarios activos con rol delegado.
     */
    private function validated(GamesEdition $edition, array $data): array
    {
        $groupId = $this->nullableId($data['group_id'] ?? null);
        $delegateId = $this->nullableId($data['delegate_id'] ?? null);
        $substituteId = $this->nullableId($data['substitute_delegate_id'] ?? null);

        if ($groupId && ! $edition->groups()->whereKey($groupId)->exists()) {
            throw ValidationException::withMessages(['form.group_id' => 'La serie no pertenece a esta olimpiada.']);
        }

        foreach (['delegate_id' => $delegateId, 'substitute_delegate_id' => $substituteId] as $field => $userId) {
            if ($userId && ! User::role('delegado')->where('is_active', true)->whereKey($userId)->exists()) {
                throw ValidationException::withMessages(["form.{$field}" => 'Debe ser un usuario activo con rol delegado.']);
            }
        }

        if ($delegateId && $delegateId === $substituteId) {
            throw ValidationException::withMessages(['form.substitute_delegate_id' => 'El suplente debe ser una persona distinta del titular.']);
        }

        $status = EditionTeamStatus::tryFrom((string) ($data['status'] ?? EditionTeamStatus::Registered->value));

        if (! $status) {
            throw ValidationException::withMessages(['form.status' => 'Estado no válido.']);
        }

        $observations = trim((string) ($data['observations'] ?? ''));

        return [
            'group_id' => $groupId,
            'delegate_id' => $delegateId,
            'substitute_delegate_id' => $substituteId,
            'status' => $status->value,
            'fee_paid' => (bool) ($data['fee_paid'] ?? false),
            'observations' => $observations === '' ? null : $observations,
        ];
    }

    private function nullableId(mixed $value): ?int
    {
        return in_array($value, ['', null, 0, '0'], true) ? null : (int) $value;
    }
}
