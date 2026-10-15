<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\RoundType;
use App\Enums\StageMode;
use App\Enums\StandardStages;
use App\Models\GamesEditionGroup;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\Sport;
use App\Models\User;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fases de una competencia. Las bases (Art. 8 y 10) juegan los deportes de
 * enfrentamiento en series por eliminacion y una fase final; el ajedrez en
 * series por sistema suizo fuera del sistema (D18) y el resto solo premia
 * 1.o, 2.o y 3.o. La estructura estandar es un dato del deporte
 * (sports.standard_stages) y las fases son datos que el organizador crea,
 * edita o cambia de modo.
 *
 * Las gestiona quien gestiona la competencia (SeasonAccess::manages()).
 */
class StageService
{
    public const FINAL_STAGE = 'Fase final';

    public const PODIUM_STAGE = 'Final';

    public function __construct(private GameMatchService $matches)
    {
    }

    /**
     * @param array{name: string, sort_order?: int|string|null, mode: string, group_id?: int|string|null, qualifiers?: int|string|null, has_third_place?: bool} $data
     */
    public function register(Season $season, array $data, User $actor): SeasonStage
    {
        $this->ensureManages($actor, $season);

        $stage = $season->stages()->create($this->validated($season, $data));
        $this->refreshAwards($season);

        return $stage;
    }

    /**
     * @param array{name: string, sort_order?: int|string|null, mode: string, group_id?: int|string|null, qualifiers?: int|string|null, has_third_place?: bool} $data
     */
    public function update(SeasonStage $stage, array $data, User $actor): SeasonStage
    {
        $this->ensureManages($actor, $stage->season);
        $values = $this->validated($stage->season, $data, $stage);

        if ($values['mode'] !== $stage->mode && $this->hasHistory($stage)) {
            throw ValidationException::withMessages([
                'form.mode' => 'No se puede cambiar el modo: la fase ya tiene partidos o podio registrado.',
            ]);
        }

        $stage->update($values);
        $this->refreshAwards($stage->season);

        return $stage;
    }

    /**
     * Una fase con partidos o con podio es historial: no se borra.
     * Devuelve true o el mensaje exacto para el toast.
     */
    public function delete(SeasonStage $stage, User $actor): bool|string
    {
        $this->ensureManages($actor, $stage->season);

        if ($stage->results()->exists()) {
            return 'No se puede eliminar: la fase tiene un podio registrado. Borra el podio primero.';
        }

        if ($this->hasMatches($stage)) {
            return 'No se puede eliminar: la fase tiene partidos. Elimínalos primero.';
        }

        try {
            $stage->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la fase tiene historial asociado.';
        }

        $this->refreshAwards($stage->season);

        return true;
    }

    /**
     * Crea las fases que piden las bases, solo si la competencia aun no
     * tiene ninguna (devuelve una coleccion vacia si ya tenia). La
     * estructura sale de standardStagesFor():
     *   - BracketSeries: una fase de partidos por cada serie de la
     *     olimpiada (clasifica 1) y una "Fase final" con tercer puesto.
     *   - PodiumSeries: una fase de podio directo por serie (clasifica 1)
     *     y una "Final" de podio directo.
     *   - PodiumOnly: una sola "Final" de podio directo.
     */
    public function createStandardStages(Season $season, User $actor): Collection
    {
        $this->ensureManages($actor, $season);

        if ($season->stages()->exists()) {
            return collect();
        }

        $season->loadMissing('sport', 'discipline', 'gamesEdition.groups');
        $structure = $this->standardStagesFor($season);

        return DB::transaction(function () use ($season, $structure) {
            if ($structure === StandardStages::PodiumOnly) {
                return collect([$this->createFinal($season, StageMode::DirectPodium, 1)]);
            }

            $mode = $structure === StandardStages::BracketSeries ? StageMode::Matches : StageMode::DirectPodium;
            $stages = collect();
            $order = 0;

            foreach ($season->gamesEdition->groups as $group) {
                $stages->push($season->stages()->create([
                    'name' => $group->name, 'sort_order' => ++$order, 'mode' => $mode,
                    'group_id' => $group->id, 'qualifiers' => 1, 'has_third_place' => false,
                ]));
            }

            $stages->push($this->createFinal($season, $mode, ++$order));

            return $stages;
        });
    }

    /**
     * createStandardStages() para cada competencia de $seasons que aun no
     * tenga fases. Cada una se autoriza por separado.
     *
     * @param  iterable<Season>  $seasons
     * @return array{seasons: int, stages: int, existing: int} competencias con fases nuevas, fases creadas y competencias que ya tenian
     */
    public function createMissingStandardStages(iterable $seasons, User $actor): array
    {
        $result = ['seasons' => 0, 'stages' => 0, 'existing' => 0];

        foreach ($seasons as $season) {
            $created = $this->createStandardStages($season, $actor);

            if ($created->isEmpty()) {
                $result['existing']++;
            } else {
                $result['seasons']++;
                $result['stages'] += $created->count();
            }
        }

        return $result;
    }

    /**
     * Lo que define el deporte (sports.standard_stages) o, si esta vacio,
     * lo que corresponde al formato efectivo (la prueba manda: la Posta
     * 4x100 de atletismo es de podio aunque el deporte cambie).
     */
    public function standardStagesFor(Season $season): StandardStages
    {
        $season->loadMissing('sport', 'discipline');

        // Se lee de la BD y no de $season->sport: quien llama puede haber
        // cargado el deporte con columnas parciales (p. ej.
        // SeasonAccess::manageableSeasons()) y el dato llegaria vacio.
        return Sport::whereKey($season->sport_id)->first(['id', 'standard_stages'])?->standard_stages
            ?? StandardStages::forFormat($this->matches->formatFor($season, $season->discipline));
    }

    private function createFinal(Season $season, StageMode $mode, int $order): SeasonStage
    {
        return $season->stages()->create([
            'name' => $mode === StageMode::Matches ? self::FINAL_STAGE : self::PODIUM_STAGE,
            'sort_order' => $order,
            'mode' => $mode,
            'has_third_place' => $mode === StageMode::Matches,
        ]);
    }

    /**
     * Clasificados de una fase de partidos ya definida: el ganador de su
     * final y, si la fase clasifica a 2 o mas, tambien el finalista.
     * Vacio mientras la final no tenga resultado.
     *
     * @return Collection<int, \App\Models\SeasonTeam>
     */
    public function winners(SeasonStage $stage): Collection
    {
        $final = $stage->matches()
            ->where('round_type', RoundType::Final->value)
            ->whereIn('status', [MatchStatus::Played->value, MatchStatus::Walkover->value])
            ->with('participants.participant')
            ->first();

        if ($final === null) {
            return collect();
        }

        $places = ($stage->qualifiers ?? 1) >= 2 ? [1, 2] : [1];

        return $final->participants
            ->whereIn('position', $places)
            ->sortBy('position')
            ->map(fn ($p) => $p->participant)
            ->filter()
            ->values();
    }

    private function ensureManages(User $actor, Season $season): void
    {
        if (! SeasonAccess::manages($actor, $season)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador gestionan las fases de esta competencia.');
        }
    }

    /**
     * El podio de una competencia sale de su ULTIMA fase: crear, reordenar
     * o borrar una fase puede cambiar el puntaje general.
     */
    private function refreshAwards(Season $season): void
    {
        $season->loadMissing('gamesEdition');
        app(AwardService::class)->rebuild($season->gamesEdition);
    }

    private function hasHistory(SeasonStage $stage): bool
    {
        return $stage->results()->exists() || $this->hasMatches($stage);
    }

    private function hasMatches(SeasonStage $stage): bool
    {
        return $stage->matches()->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Season $season, array $data, ?SeasonStage $current = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $errors = [];

        if ($name === '') {
            $errors['form.name'] = 'El nombre de la fase es obligatorio.';
        } elseif ($season->stages()->where('name', $name)->when($current, fn ($q) => $q->whereKeyNot($current->id))->exists()) {
            $errors['form.name'] = 'Ya existe una fase con ese nombre en esta competencia.';
        }

        $mode = StageMode::tryFrom((string) ($data['mode'] ?? ''));
        if ($mode === null) {
            $errors['form.mode'] = 'Elige cómo se juega la fase.';
        }

        $groupId = $this->intOrNull($data['group_id'] ?? null);
        if ($groupId !== null && ! GamesEditionGroup::whereKey($groupId)->where('games_edition_id', $season->games_edition_id)->exists()) {
            $errors['form.group_id'] = 'La serie no pertenece a la olimpiada de esta competencia.';
        }

        $qualifiers = $this->intOrNull($data['qualifiers'] ?? null);
        if ($qualifiers !== null && $qualifiers < 1) {
            $errors['form.qualifiers'] = 'Los clasificados deben ser al menos 1.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => $name,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'mode' => $mode,
            'group_id' => $groupId,
            'qualifiers' => $qualifiers,
            // El tercer puesto solo tiene sentido en una fase de partidos.
            'has_third_place' => $mode === StageMode::Matches && (bool) ($data['has_third_place'] ?? false),
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
