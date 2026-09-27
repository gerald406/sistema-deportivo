<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeasonStatus;
use App\Models\EventParticipant;
use App\Models\Player;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\SportPosition;
use App\Models\User;
use App\Policies\SeasonTeamPlayerPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Plantel (roster) de un equipo inscrito. Reglas confirmadas (Fase 5):
 *   - Un jugador no puede estar en dos equipos de la misma temporada.
 *   - Edad del jugador a la fecha de inicio de la temporada dentro del
 *     rango min/max de la categoria de la inscripcion (si lo tiene).
 *     players no tiene genero: la categoria solo se valida por edad.
 *   - Numero de camiseta unico dentro del equipo; un solo capitan.
 *   - La posicion debe ser del deporte de la temporada (no hay trigger).
 * Decisiones propias: una temporada Cerrada no admite cambios de plantel;
 * si ya hay capitan se rechaza (no se reasigna en silencio).
 */
class SeasonTeamPlayerService
{
    /**
     * @param array{season_team_id: int, player_id: int, shirt_number: int|null, position_id: int|null, is_captain: bool, status: string, enrolled_at: string, observations: string|null} $data
     */
    public function register(array $data, User $actor): SeasonTeamPlayer
    {
        $seasonTeam = SeasonTeam::with('season.tournament', 'category', 'team.delegate', 'delegate')->findOrFail($data['season_team_id']);

        if (! SeasonTeamPlayerPolicy::managesSeasonTeam($actor, $seasonTeam)) {
            throw new AuthorizationException('Solo el delegado del equipo o el organizador del torneo gestionan este plantel.');
        }

        $player = Player::findOrFail($data['player_id']);

        $this->ensureSeasonOpen($seasonTeam);

        if (! $player->is_active) {
            throw ValidationException::withMessages(['form.player_id' => 'El jugador está inactivo.']);
        }

        $clash = SeasonTeamPlayer::where('player_id', $player->id)
            ->whereHas('seasonTeam', fn ($q) => $q->where('season_id', $seasonTeam->season_id))
            ->with('seasonTeam.team:id,name')
            ->first();

        if ($clash) {
            throw ValidationException::withMessages([
                'form.player_id' => $clash->season_team_id === $seasonTeam->id
                    ? 'El jugador ya está en este plantel.'
                    : "El jugador ya está inscrito en {$clash->seasonTeam->team->name} en esta temporada.",
            ]);
        }

        $this->ensureAgeFitsCategory($player, $seasonTeam);
        $this->ensureRosterRules($seasonTeam, $data);

        return SeasonTeamPlayer::create([
            'season_team_id' => $seasonTeam->id,
            'player_id' => $player->id,
            'shirt_number' => $data['shirt_number'] ?? null,
            'position_id' => $data['position_id'] ?? null,
            'is_captain' => (bool) ($data['is_captain'] ?? false),
            'status' => $data['status'] ?? 'active',
            'enrolled_at' => $data['enrolled_at'] ?? now()->toDateString(),
            'observations' => $data['observations'] ?? null,
        ]);
    }

    /**
     * Equipo y jugador no se cambian (seria otra ficha): solo datos de la
     * ficha de plantel.
     */
    public function update(SeasonTeamPlayer $entry, array $data): SeasonTeamPlayer
    {
        $entry->load('seasonTeam.season'); // load (no loadMissing): el estado de la temporada debe leerse fresco
        $this->ensureSeasonOpen($entry->seasonTeam);
        $this->ensureRosterRules($entry->seasonTeam, $data, $entry->id);

        $entry->update([
            'shirt_number' => $data['shirt_number'] ?? null,
            'position_id' => $data['position_id'] ?? null,
            'is_captain' => (bool) ($data['is_captain'] ?? false),
            'status' => $data['status'],
            'enrolled_at' => $data['enrolled_at'],
            'observations' => $data['observations'] ?? null,
        ]);

        return $entry;
    }

    /**
     * match_events, suspensions y event_lineups son RESTRICT sobre la
     * ficha; event_participants (atletismo/ajedrez) no tiene FK real. Un
     * jugador con historial no se quita: se le cambia el status a Baja.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(SeasonTeamPlayer $entry): bool|string
    {
        $entry->load('seasonTeam.season'); // load (no loadMissing): el estado de la temporada debe leerse fresco

        if ($entry->seasonTeam->season->status === SeasonStatus::Closed) {
            return 'No se puede quitar: la temporada está cerrada.';
        }

        if ($entry->matchEvents()->exists() || $entry->suspensions()->exists()
            || $entry->lineups()->exists() || $entry->eventParticipants()->exists()) {
            return 'No se puede quitar: el jugador ya tiene historial en la temporada. Cámbiale el estado a "Baja" en su lugar.';
        }

        try {
            $entry->delete();
        } catch (QueryException) {
            return 'No se puede quitar: el jugador tiene historial asociado. Cámbiale el estado a "Baja" en su lugar.';
        }

        return true;
    }

    /** Inscripciones cuyo plantel puede gestionar $actor (temporadas no cerradas). */
    public function seasonTeamsFor(User $actor): Collection
    {
        return SeasonTeam::query()
            ->with(['team:id,name,delegate_id', 'season:id,name,status,tournament_id,sport_id', 'season.tournament:id,name,organizer_id', 'delegate:id'])
            ->whereHas('season', fn ($q) => $q->where('status', '!=', SeasonStatus::Closed->value))
            ->get()
            ->filter(fn (SeasonTeam $st) => SeasonTeamPlayerPolicy::managesSeasonTeam($actor, $st))
            ->sortBy(fn (SeasonTeam $st) => $st->team->name)
            ->values();
    }

    /** Edad cumplida a la fecha de referencia de la temporada. */
    public function ageAtSeason(Player $player, SeasonTeam $seasonTeam): ?int
    {
        if (! $player->birth_date) {
            return null;
        }

        $reference = $seasonTeam->season->start_date ?? Carbon::today();

        return (int) $player->birth_date->diffInYears($reference);
    }

    private function ensureSeasonOpen(SeasonTeam $seasonTeam): void
    {
        if ($seasonTeam->season->status === SeasonStatus::Closed) {
            throw ValidationException::withMessages(['form.season_team_id' => 'La temporada está cerrada: el plantel ya no se modifica.']);
        }
    }

    private function ensureAgeFitsCategory(Player $player, SeasonTeam $seasonTeam): void
    {
        $category = $seasonTeam->category;

        if (! $category || ($category->min_age === null && $category->max_age === null)) {
            return;
        }

        $age = $this->ageAtSeason($player, $seasonTeam);

        if ($age === null) {
            throw ValidationException::withMessages(['form.player_id' => 'El jugador no tiene fecha de nacimiento: no se puede validar la categoría.']);
        }

        if (($category->min_age !== null && $age < $category->min_age) || ($category->max_age !== null && $age > $category->max_age)) {
            $range = ($category->min_age ?? '—') . ' a ' . ($category->max_age ?? '—');
            throw ValidationException::withMessages([
                'form.player_id' => "El jugador tiene {$age} años al inicio de la temporada; la categoría {$category->name} admite de {$range}.",
            ]);
        }
    }

    private function ensureRosterRules(SeasonTeam $seasonTeam, array $data, ?int $ignoreId = null): void
    {
        $others = SeasonTeamPlayer::where('season_team_id', $seasonTeam->id)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));

        if (($data['shirt_number'] ?? null) !== null && (clone $others)->where('shirt_number', $data['shirt_number'])->exists()) {
            throw ValidationException::withMessages(['form.shirt_number' => "El número {$data['shirt_number']} ya lo usa otro jugador del equipo."]);
        }

        if (! empty($data['is_captain']) && (clone $others)->where('is_captain', true)->exists()) {
            throw ValidationException::withMessages(['form.is_captain' => 'El equipo ya tiene capitán. Quítale la capitanía primero.']);
        }

        if (($data['position_id'] ?? null) !== null
            && ! SportPosition::whereKey($data['position_id'])->where('sport_id', $seasonTeam->season->sport_id)->exists()) {
            throw ValidationException::withMessages(['form.position_id' => 'La posición no pertenece al deporte de la temporada.']);
        }
    }
}
