<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Gender;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Niveles de las bases (VI): Libre 25+, Master 40+, Super Master 55+. El
 * nivel no lleva genero (X): damas y varones se separan por competencia.
 * La edad minima se compara por ANIO de nacimiento (D10).
 */
class CategorySeeder extends Seeder
{
    /** nombre => [edad minima, nombres anteriores del mismo nivel]. */
    private const LEVELS = [
        'Libre' => [25, []],
        'Máster' => [40, ['Master']],
        'Súper Máster' => [55, ['Senior', 'Super Master']],
    ];

    /** Categorias del sistema generico anterior: se desactivan, no se borran. */
    private const LEGACY = ['Sub-17', 'Damas', 'Varones'];

    public function run(): void
    {
        $kept = [];

        foreach (self::LEVELS as $name => [$minAge, $aliases]) {
            // Reutiliza la fila mas antigua con ese nombre (o uno anterior)
            // para no dejar inscripciones apuntando a un nivel huerfano.
            $level = Category::whereIn('name', [$name, ...$aliases])->orderBy('id')->first() ?? new Category();
            $level->fill([
                'name' => $name,
                'min_age' => $minAge,
                'max_age' => null,
                'gender' => Gender::Mixed,
                'is_active' => true,
            ])->save();

            $kept[$name] = $level->id;
        }

        $names = array_merge(self::LEGACY, array_keys(self::LEVELS), ...array_column(self::LEVELS, 1));

        // Duplicados por genero (Libre damas / Libre varones) y categorias
        // del sistema anterior.
        Category::whereIn('name', $names)
            ->whereNotIn('id', $kept)
            ->update(['is_active' => false]);
    }
}
