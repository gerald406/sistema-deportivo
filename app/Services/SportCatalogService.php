<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Discipline;
use App\Models\Sport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * CRUD de Sport y Discipline. Ambos son catalogos independientes (Sport)
 * o casi-independientes (Discipline solo depende de Sport), por eso se
 * implementan antes que Tournament/Season, que dependen de estos.
 */
class SportCatalogService
{
    public function registerSport(array $data): Sport
    {
        $data['slug'] = $data['slug'] !== '' ? Str::slug($data['slug']) : Str::slug($data['name']);

        return Sport::create($data);
    }

    public function updateSport(Sport $sport, array $data): Sport
    {
        $data['slug'] = $data['slug'] !== '' ? Str::slug($data['slug']) : Str::slug($data['name']);

        $sport->update($data);

        return $sport;
    }

    /**
     * disciplines y seasons usan RESTRICT sobre sport_id (sin soft
     * deletes): un deporte con disciplinas o temporadas no se puede
     * borrar. Se detecta antes de intentarlo en vez de dejar que MySQL
     * truene con un 1451. (sport_positions y event_types son CASCADE.)
     */
    public function deleteSport(Sport $sport): bool
    {
        if ($sport->disciplines()->exists() || $sport->seasons()->exists()) {
            return false;
        }

        try {
            $sport->delete();
        } catch (QueryException) {
            return false;
        }

        return true;
    }

    public function registerDiscipline(Sport $sport, array $data): Discipline
    {
        $data['sport_id'] = $sport->id;

        return Discipline::create($data);
    }

    public function updateDiscipline(Discipline $discipline, array $data): Discipline
    {
        $discipline->update($data);

        return $discipline;
    }

    /**
     * matches.discipline_id es SET NULL en la BD: borrar una disciplina
     * dejaria resultados sin prueba asociada. Por eso se bloquea aqui si
     * ya se uso en algun partido.
     */
    public function deleteDiscipline(Discipline $discipline): bool
    {
        if ($discipline->matches()->exists()) {
            return false;
        }

        try {
            $discipline->delete();
        } catch (QueryException) {
            return false;
        }

        return true;
    }
}
