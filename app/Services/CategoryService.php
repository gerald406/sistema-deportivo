<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\QueryException;

class CategoryService
{
    /**
     * @param array{name: string, min_age: int|null, max_age: int|null, gender: string, is_active: bool} $data
     */
    public function register(array $data): Category
    {
        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    /**
     * season_team.category_id es SET NULL en la BD: borrar una categoria
     * en uso dejaria inscripciones sin categoria en silencio. Por eso se
     * bloquea aqui (mismo criterio que deleteDiscipline).
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function delete(Category $category): bool|string
    {
        if ($category->seasonTeams()->exists()) {
            return 'No se puede eliminar: la categoría tiene equipos inscritos. Desactívala en su lugar.';
        }

        try {
            $category->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la categoría tiene historial asociado. Desactívala en su lugar.';
        }

        return true;
    }
}
