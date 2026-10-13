<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GenderScope;
use App\Models\Category;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Cuadro "Puntaje total por categorias" de la olimpiada (pag. 9 de las
 * bases). Lo configura SOLO el admin (docs/fases/00-especificacion.md, D7);
 * el organizador de la olimpiada lo ve pero no lo edita.
 *
 * Los puntos y los topes de plantel son opcionales (D6): vacio = la fila no
 * suma al puntaje general / no hay restriccion.
 */
class EditionEventService
{
    private const POINT_FIELDS = ['pts_first' => '1.º puesto', 'pts_second' => '2.º puesto', 'pts_third' => '3.º puesto'];

    /**
     * @param array<string, mixed> $data label, sort_order, sport_id, category_id, gender_scope,
     *                                   separate_podiums, per_discipline, pts_first, pts_second,
     *                                   pts_third, min_players, max_players, rules_blocking, is_active
     */
    public function register(GamesEdition $edition, array $data, User $actor): GamesEditionEvent
    {
        $this->ensureAdmin($actor);

        return $edition->events()->create($this->validated($edition, $data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(GamesEditionEvent $event, array $data, User $actor): GamesEditionEvent
    {
        $this->ensureAdmin($actor);

        $event->update($this->validated($event->edition, $data, $event->id));

        return $event;
    }

    /** Devuelve true o el mensaje exacto para el toast. */
    public function delete(GamesEditionEvent $event, User $actor): bool|string
    {
        $this->ensureAdmin($actor);

        try {
            $event->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la fila tiene historial asociado. Desactívala en su lugar.';
        }

        return true;
    }

    /**
     * Se revalida aqui y no solo en el componente: cualquier metodo publico
     * de Livewire es invocable desde el navegador.
     */
    private function ensureAdmin(User $actor): void
    {
        if (! $actor->hasRole('admin')) {
            throw new AuthorizationException('Solo el administrador configura el cuadro de puntaje.');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(GamesEdition $edition, array $data, ?int $ignoreId = null): array
    {
        $label = trim((string) ($data['label'] ?? ''));
        $errors = [];

        if ($label === '') {
            $errors['form.label'] = 'La etiqueta es obligatoria.';
        } elseif ($edition->events()->where('label', $label)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $errors['form.label'] = 'Ya existe una fila con esa etiqueta en esta olimpiada.';
        }

        $scope = GenderScope::tryFrom((string) ($data['gender_scope'] ?? GenderScope::None->value));
        if ($scope === null) {
            $errors['form.gender_scope'] = 'El alcance no es válido.';
        }

        $sportId = $this->intOrNull($data['sport_id'] ?? null);
        if ($sportId !== null && ! Sport::whereKey($sportId)->exists()) {
            $errors['form.sport_id'] = 'El deporte no existe.';
        }

        $categoryId = $this->intOrNull($data['category_id'] ?? null);
        if ($categoryId !== null && ! Category::whereKey($categoryId)->exists()) {
            $errors['form.category_id'] = 'El nivel no existe.';
        }

        // Puntos: no negativos y, entre los que esten definidos, ningun
        // puesto inferior da mas que uno superior.
        $points = [];
        $previous = null;
        foreach (self::POINT_FIELDS as $field => $name) {
            $points[$field] = $this->intOrNull($data[$field] ?? null);

            if ($points[$field] === null) {
                continue;
            }

            if ($points[$field] < 0) {
                $errors["form.{$field}"] = "Los puntos del {$name} no pueden ser negativos.";
            } elseif ($previous !== null && $points[$field] > $previous) {
                $errors["form.{$field}"] = "El {$name} no puede dar más puntos que un puesto superior.";
            }

            $previous = $points[$field];
        }

        $min = $this->intOrNull($data['min_players'] ?? null);
        $max = $this->intOrNull($data['max_players'] ?? null);

        if ($min !== null && $min < 0) {
            $errors['form.min_players'] = 'El mínimo no puede ser negativo.';
        }
        if ($max !== null && $max < 1) {
            $errors['form.max_players'] = 'El máximo debe ser al menos 1.';
        } elseif ($min !== null && $max !== null && $min > $max) {
            $errors['form.max_players'] = 'El máximo no puede ser menor que el mínimo.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $points + [
            'label' => $label,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'sport_id' => $sportId,
            'category_id' => $categoryId,
            'gender_scope' => $scope,
            'separate_podiums' => (bool) ($data['separate_podiums'] ?? true),
            'per_discipline' => (bool) ($data['per_discipline'] ?? false),
            'min_players' => $min,
            'max_players' => $max,
            'rules_blocking' => (bool) ($data['rules_blocking'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
