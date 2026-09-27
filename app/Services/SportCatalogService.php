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
     * disciplines usa RESTRICT sobre sport_id (sin soft deletes): un
     * deporte con disciplinas ya creadas no se puede borrar. Se detecta
     * antes de intentarlo en vez de dejar que MySQL truene con un 1451.
     */
    public function deleteSport(Sport $sport): bool
    {
        if ($sport->disciplines()->exists()) {
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
     * matches usa RESTRICT sobre discipline_id: una disciplina ya usada
     * en algun partido no se puede borrar.
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
