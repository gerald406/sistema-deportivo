<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EditionTeamStatus;
use App\Enums\SeasonStatus;
use App\Enums\SeasonTeamPlayerStatus;
use App\Models\EventParticipant;
use App\Models\GamesEditionTeam;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\SportPosition;
use App\Models\Team;
use App\Models\User;
use App\Policies\SeasonTeamPlayerPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plantel de una delegacion en una competencia.
 *
 * Bloqueos fijos, por integridad de los datos:
 *   - Un participante no esta dos veces en la misma competencia.
 *   - Numero de camiseta unico dentro del plantel; un solo capitan.
 *   - La posicion debe ser del deporte de la competencia (no hay trigger).
 *   - Una competencia Cerrada no admite cambios de plantel.
 *
 * Reglas de reglamento (genero, edad por anio, tope, una sola delegacion
 * por olimpiada): son datos y las evalua RosterRuleService. Advierten por
 * defecto y solo bloquean si la fila del cuadro lo pide (D11).
 *
 * La inscripcion de la delegacion en la competencia (SeasonTeam) nace sola
 * al registrar su primer participante: registerInCompetition() (S3).
 */
class SeasonTeamPlayerService
{
    /** Tope de participantes por alta multiple (registerMany()). */
    public const BULK_MAX = 100;

    /** Campos que se editan en linea desde la tabla del plantel (updateInline()). */
    public const INLINE_FIELDS = ['shirt_number', 'position_id', 'is_captain'];

    public function __construct(private RosterRuleService $rules, private GamesEditionService $editions)
    {
    }

    /**
     * Agrega un participante al plantel de $team en $season, creando la
     * inscripcion de la delegacion si aun no existe. Es la entrada del
     * delegado: elige competencia y delegacion, no una inscripcion previa.
     *
     * @param array{player_id: int, shirt_number?: int|null, position_id?: int|null, is_captain?: bool, status?: string, enrolled_at?: string, observations?: string|null} $data
     */
    public function registerInCompetition(Season $season, Team $team, array $data, User $actor): SeasonTeamPlayer
    {
        if (! $this->canRegisterFor($actor, $season, $team)) {
            throw new AuthorizationException('Solo los delegados de la delegación o el organizador de la olimpiada gestionan este plantel.');
        }

        if ($season->status === SeasonStatus::Closed) {
            throw ValidationException::withMessages(['form.season_id' => 'La competencia está cerrada: el plantel ya no se modifica.']);
        }

        // Si este participante crearia la inscripcion en la disciplina, rige
        // lo mismo que al inscribirla (D22): el delegado solo hasta el cierre.
        if (! SeasonTeam::where('season_id', $season->id)->where('team_id', $team->id)->exists()) {
            try {
                app(DelegationEnrollmentService::class)->ensureCanEnroll($actor, $season, $team);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['form.season_id' => $e->errors()['enrollment'] ?? $e->getMessage()]);
            }
        }

        // Transaccion: si el participante es rechazado, la inscripcion de
        // la delegacion recien creada no queda vacia.
        return DB::transaction(function () use ($season, $team, $data, $actor) {
            $seasonTeam = app(SeasonTeamService::class)->ensureFor($season, $team);

            return $this->register(['season_team_id' => $seasonTeam->id] + $data, $actor);
        });
    }

    /**
     * Alta de varios participantes de una vez (D23): cada uno pasa por
     * registerInCompetition(), con todas sus reglas, y entra con camiseta y
     * posicion vacias, sin capitania y habilitado. Se agregan los validos y
     * se informa el resto, para no perder toda la seleccion por un caso.
     *
     * @param  list<int|string>  $playerIds
     * @return array{added: list<string>, warnings: list<string>, rejected: list<string>}
     */
    public function registerMany(Season $season, Team $team, array $playerIds, User $actor): array
    {
        if (! $this->canRegisterFor($actor, $season, $team)) {
            throw new AuthorizationException('Solo los delegados de la delegación o el organizador de la olimpiada gestionan este plantel.');
        }

        $ids = collect($playerIds)->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['bulk' => 'Selecciona al menos un participante.']);
        }

        if ($ids->count() > self::BULK_MAX) {
            throw ValidationException::withMessages(['bulk' => 'Puedes agregar hasta '.self::BULK_MAX.' participantes por vez.']);
        }

        $result = ['added' => [], 'warnings' => [], 'rejected' => []];

        foreach (Player::whereIn('id', $ids)->orderBy('last_name')->orderBy('first_name')->get() as $player) {
            $name = $player->fullName();

            try {
                $this->registerInCompetition($season, $team, [
                    'player_id' => $player->id, 'shirt_number' => null, 'position_id' => null, 'is_captain' => false,
                    'status' => SeasonTeamPlayerStatus::Active->value, 'enrolled_at' => now()->toDateString(), 'observations' => null,
                ], $actor);

                $result['added'][] = $name;

                foreach ($this->rules->evaluate($season, $team, $player)['warnings'] as $warning) {
                    $result['warnings'][] = "{$name}: {$warning}";
                }
            } catch (ValidationException $e) {
                $result['rejected'][] = "{$name}: ".collect($e->errors())->flatten()->first();
            }
        }

        return $result;
    }

    /**
     * Edicion en linea desde la tabla del plantel (D23): un solo campo
     * (camiseta, posicion o capitania), con las mismas reglas que update().
     * Marcar a alguien como capitan le quita la capitania al anterior: un
     * capitan por plantel sin pasos extra.
     */
    public function updateInline(SeasonTeamPlayer $entry, string $field, mixed $value, User $actor): SeasonTeamPlayer
    {
        $entry->load('seasonTeam.season.gamesEdition');

        if (! SeasonTeamPlayerPolicy::managesSeasonTeam($actor, $entry->seasonTeam)) {
            throw new AuthorizationException('Solo el delegado del equipo o el organizador de la olimpiada gestionan este plantel.');
        }

        if (! in_array($field, self::INLINE_FIELDS, true)) {
            throw ValidationException::withMessages(['inline' => 'Ese dato no se edita desde la tabla.']);
        }

        $value = match ($field) {
            'is_captain' => (bool) $value,
            default => $value === '' || $value === null ? null : (int) $value,
        };

        if ($field === 'shirt_number' && $value !== null && ($value < 0 || $value > 999)) {
            throw ValidationException::withMessages(['form.shirt_number' => 'La camiseta debe ser un número de 0 a 999.']);
        }

        $data = [
            'shirt_number' => $entry->shirt_number,
            'position_id' => $entry->position_id,
            'is_captain' => $entry->is_captain,
            'status' => $entry->status->value,
            'enrolled_at' => $entry->enrolled_at->toDateString(),
            'observations' => $entry->observations,
            $field => $value,
        ];

        return DB::transaction(function () use ($entry, $field, $value, $data) {
            if ($field === 'is_captain' && $value) {
                SeasonTeamPlayer::where('season_team_id', $entry->season_team_id)->whereKeyNot($entry->id)->update(['is_captain' => false]);
            }

            return $this->update($entry, $data);
        });
    }

    /**
     * Quien puede armar el plantel de $team en $season: el admin, quien
     * gestiona la olimpiada, o el delegado titular o suplente de ESA
     * delegacion en ESA olimpiada. Misma regla que
     * SeasonTeamPlayerPolicy::managesSeasonTeam(), pero sin necesitar que
     * la inscripcion a la competencia exista todavia.
     */
    public function canRegisterFor(User $actor, Season $season, Team $team): bool
    {
        $season->loadMissing('gamesEdition');

        if ($season->gamesEdition && $this->editions->manages($actor, $season->gamesEdition)) {
            return true;
        }

        return $actor->hasRole('delegado')
            && $actor->can('players.manage')
            && GamesEditionTeam::where('games_edition_id', $season->games_edition_id)
                ->where('team_id', $team->id)
                ->where(fn ($q) => $q->where('delegate_id', $actor->id)->orWhere('substitute_delegate_id', $actor->id))
                ->exists();
    }

    /**
     * Competencias abiertas en las que $actor puede armar planteles
     * (union si tiene varios roles): admin, todas; organizador, las de sus
     * olimpiadas; delegado, las de las olimpiadas donde tiene una
     * delegacion inscrita.
     */
    public function competitionsFor(User $actor): Collection
    {
        return Season::query()
            ->where('status', '!=', SeasonStatus::Closed->value)
            ->where('is_active', true)
            ->unless($actor->hasRole('admin'), fn ($q) => $q->whereHas('gamesEdition', function ($edition) use ($actor) {
                $edition->where(function ($w) use ($actor) {
                    $w->whereRaw('1 = 0');

                    if ($actor->hasRole('organizador') && $actor->can('editions.manage')) {
                        $w->orWhere('organizer_id', $actor->id);
                    }

                    if ($actor->hasRole('delegado') && $actor->can('players.manage')) {
                        $w->orWhereHas('editionTeams', fn ($t) => $t
                            ->where('status', EditionTeamStatus::Registered->value)
                            ->where(fn ($d) => $d->where('delegate_id', $actor->id)->orWhere('substitute_delegate_id', $actor->id)));
                    }
                });
            }))
            ->with(['gamesEdition:id,name,organizer_id,start_date', 'event:id,min_players,max_players,rules_blocking'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Delegaciones para las que $actor puede armar plantel en $season:
     * quien gestiona la olimpiada, todas las inscritas; el delegado, las
     * suyas. Vacio si no le corresponde ninguna.
     */
    public function delegationsFor(User $actor, Season $season): Collection
    {
        $season->loadMissing('gamesEdition');
        $manages = $season->gamesEdition && $this->editions->manages($actor, $season->gamesEdition);

        if (! $manages && ! ($actor->hasRole('delegado') && $actor->can('players.manage'))) {
            return collect();
        }

        return Team::query()
            ->whereHas('editionTeams', fn ($t) => $t
                ->where('games_edition_id', $season->games_edition_id)
                ->where('status', EditionTeamStatus::Registered->value)
                ->unless($manages, fn ($q) => $q->where(fn ($d) => $d->where('delegate_id', $actor->id)->orWhere('substitute_delegate_id', $actor->id))))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param array{season_team_id: int, player_id: int, shirt_number: int|null, position_id: int|null, is_captain: bool, status: string, enrolled_at: string, observations: string|null} $data
     */
    public function register(array $data, User $actor): SeasonTeamPlayer
    {
        $seasonTeam = SeasonTeam::with('season.gamesEdition', 'season.event', 'season.category', 'team')->findOrFail($data['season_team_id']);

        if (! SeasonTeamPlayerPolicy::managesSeasonTeam($actor, $seasonTeam)) {
            throw new AuthorizationException('Solo el delegado del equipo o el organizador de la olimpiada gestionan este plantel.');
        }

        $player = Player::findOrFail($data['player_id']);

        // Mismo alcance que el buscador del formulario: el id llega del
        // navegador y nadie agrega a un participante de otra delegacion.
        if (! app(PlayerRegistrationService::class)->isVisibleTo($actor, $player)) {
            throw ValidationException::withMessages(['form.player_id' => 'Ese participante no pertenece a tus delegaciones.']);
        }

        $this->ensureSeasonOpen($seasonTeam);

        if (! $player->is_active) {
            throw ValidationException::withMessages(['form.player_id' => 'El participante está inactivo.']);
        }

        $clash = SeasonTeamPlayer::where('player_id', $player->id)
            ->whereHas('seasonTeam', fn ($q) => $q->where('season_id', $seasonTeam->season_id))
            ->with('seasonTeam.team:id,name')
            ->first();

        if ($clash) {
            throw ValidationException::withMessages([
                'form.player_id' => $clash->season_team_id === $seasonTeam->id
                    ? 'El participante ya está en este plantel.'
                    : "El participante ya está inscrito en {$clash->seasonTeam->team->name} en esta competencia.",
            ]);
        }

        // Reglas de reglamento: solo llegan como errores si la fila del
        // cuadro es bloqueante; si no, son advertencias y se inscribe.
        $rules = $this->rules->evaluate($seasonTeam->season, $seasonTeam->team, $player);

        if ($rules['errors'] !== []) {
            throw ValidationException::withMessages(['form.player_id' => $rules['errors']]);
        }

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
     * Equipo y participante no se cambian (seria otra ficha): solo datos de la
     * ficha de plantel.
     */
    public function update(SeasonTeamPlayer $entry, array $data): SeasonTeamPlayer
    {
        $entry->load('seasonTeam.season'); // load (no loadMissing): el estado de la competencia debe leerse fresco
        $this->ensureSeasonOpen($entry->seasonTeam);
        $this->ensureRosterRules($entry->seasonTeam, $data, $entry->id);
        $this->ensureSuspensionRespected($entry, $data);

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
     * participante con historial no se quita: se le cambia el status a Baja.
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(SeasonTeamPlayer $entry): bool|string
    {
        $entry->load('seasonTeam.season'); // load (no loadMissing): el estado de la competencia debe leerse fresco

        if ($entry->seasonTeam->season->status === SeasonStatus::Closed) {
            return 'No se puede quitar: la competencia está cerrada.';
        }

        if ($entry->matchEvents()->exists() || $entry->suspensions()->exists()
            || $entry->lineups()->exists() || $entry->eventParticipants()->exists()) {
            return 'No se puede quitar: el participante ya tiene historial en la competencia. Cámbiale el estado a "Baja" en su lugar.';
        }

        try {
            $entry->delete();
        } catch (QueryException) {
            return 'No se puede quitar: el participante tiene historial asociado. Cámbiale el estado a "Baja" en su lugar.';
        }

        return true;
    }

    /**
     * Convocados que $actor ve en /planteles: los de inscripciones que ve
     * en /inscripciones (SeasonTeamService::scopeVisibleTo(), misma regla
     * por construccion). Sin created_by: aqui no se crean participantes
     * sueltos, se convocan a una inscripcion que ya determina el alcance.
     * Tambien es la base del alcance de /participantes
     * (PlayerRegistrationService::scopeVisibleTo()).
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('admin')) {
            return $query;
        }

        return $query->whereHas('seasonTeam', fn ($st) => app(SeasonTeamService::class)->scopeVisibleTo($st, $actor));
    }

    /**
     * El convocado esta dentro del alcance de $actor (lo usa
     * SeasonTeamPlayerPolicy::view): misma regla que scopeVisibleTo().
     */
    public function isVisibleTo(User $actor, SeasonTeamPlayer $entry): bool
    {
        return $this->scopeVisibleTo(SeasonTeamPlayer::query(), $actor)->whereKey($entry->id)->exists();
    }

    private function ensureSeasonOpen(SeasonTeam $seasonTeam): void
    {
        if ($seasonTeam->season->status === SeasonStatus::Closed) {
            throw ValidationException::withMessages(['form.season_team_id' => 'La competencia está cerrada: el plantel ya no se modifica.']);
        }
    }

    /**
     * El estado "Habilitado" de un sancionado lo devuelve
     * SuspensionService::syncRosterStatus() cuando ya no le quedan sanciones
     * pendientes; desde el formulario de plantel (que tambien usa el
     * delegado del equipo) no se puede levantar una suspension.
     */
    private function ensureSuspensionRespected(SeasonTeamPlayer $entry, array $data): void
    {
        if ($data['status'] === SeasonTeamPlayerStatus::Active->value
            && $entry->suspensions()->where('is_served', false)->exists()) {
            throw ValidationException::withMessages([
                'form.status' => 'El participante tiene sanciones pendientes: no se puede habilitar hasta que se marquen como cumplidas en Sanciones.',
            ]);
        }
    }

    private function ensureRosterRules(SeasonTeam $seasonTeam, array $data, ?int $ignoreId = null): void
    {
        $others = SeasonTeamPlayer::where('season_team_id', $seasonTeam->id)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));

        if (($data['shirt_number'] ?? null) !== null && (clone $others)->where('shirt_number', $data['shirt_number'])->exists()) {
            throw ValidationException::withMessages(['form.shirt_number' => "El número {$data['shirt_number']} ya lo usa otro participante del equipo."]);
        }

        if (! empty($data['is_captain']) && (clone $others)->where('is_captain', true)->exists()) {
            throw ValidationException::withMessages(['form.is_captain' => 'El equipo ya tiene capitán. Quítale la capitanía primero.']);
        }

        if (($data['position_id'] ?? null) !== null
            && ! SportPosition::whereKey($data['position_id'])->where('sport_id', $seasonTeam->season->sport_id)->exists()) {
            throw ValidationException::withMessages(['form.position_id' => 'La posición no pertenece al deporte de la competencia.']);
        }
    }
}
