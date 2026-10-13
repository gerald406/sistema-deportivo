<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SportFormatType;
use App\Enums\StageMode;
use App\Models\GamesEditionGroup;
use App\Models\Season;
use App\Models\SeasonStage;
use App\Models\User;
use App\Support\SeasonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fases de una competencia. Las bases (Art. 8 y 10) juegan los deportes de
 * enfrentamiento en series por eliminacion y una fase final; el resto solo
 * premia 1.o, 2.o y 3.o. Nada de eso esta fijo: las fases son datos que el
 * organizador crea, edita o cambia de modo (p. ej. ajedrez a podio directo
 * si lleva el sistema suizo en papel, D14).
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

        return $season->stages()->create($this->validated($season, $data));
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

        return true;
    }

    /**
     * Crea las fases que piden las bases, solo si la competencia aun no
     * tiene ninguna (devuelve una coleccion vacia si ya tenia):
     *   - Enfrentamiento: una fase de partidos por cada serie de la
     *     olimpiada (clasifica 1) y una "Fase final" con tercer puesto.
     *   - Cualquier otro formato: una sola "Final" de podio directo.
     */
    public function createStandardStages(Season $season, User $actor): Collection
    {
        $this->ensureManages($actor, $season);

        if ($season->stages()->exists()) {
            return collect();
        }

        $season->loadMissing('sport', 'discipline', 'gamesEdition.groups');

        return DB::transaction(function () use ($season) {
            if ($this->matches->formatFor($season, $season->discipline) !== SportFormatType::HeadToHead) {
                return collect([$season->stages()->create([
                    'name' => self::PODIUM_STAGE, 'sort_order' => 1, 'mode' => StageMode::DirectPodium, 'has_third_place' => false,
                ])]);
            }

            $stages = collect();
            $order = 0;

            foreach ($season->gamesEdition->groups as $group) {
                $stages->push($season->stages()->create([
                    'name' => $group->name, 'sort_order' => ++$order, 'mode' => StageMode::Matches,
                    'group_id' => $group->id, 'qualifiers' => 1, 'has_third_place' => false,
                ]));
            }

            $stages->push($season->stages()->create([
                'name' => self::FINAL_STAGE, 'sort_order' => ++$order, 'mode' => StageMode::Matches,
                'has_third_place' => true,
            ]));

            return $stages;
        });
    }

    private function ensureManages(User $actor, Season $season): void
    {
        if (! SeasonAccess::manages($actor, $season)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador gestionan las fases de esta competencia.');
        }
    }

    private function hasHistory(SeasonStage $stage): bool
    {
        return $stage->results()->exists() || $this->hasMatches($stage);
    }

    /** matches.season_stage_id llega con las llaves: hasta entonces una fase no tiene partidos. */
    private function hasMatches(SeasonStage $stage): bool
    {
        return method_exists($stage, 'matches') && $stage->matches()->exists();
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
