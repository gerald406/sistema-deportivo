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
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function deleteSport(Sport $sport): bool|string
    {
        if ($sport->disciplines()->exists()) {
            return 'No se puede eliminar: el deporte tiene disciplinas registradas.';
        }

        if ($sport->seasons()->exists()) {
            return 'No se puede eliminar: el deporte tiene temporadas registradas. Desactívalo en su lugar.';
        }

        try {
            $sport->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: el deporte tiene historial asociado. Desactívalo en su lugar.';
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
     *
     * Devuelve true o el mensaje exacto de por que no se pudo borrar.
     */
    public function deleteDiscipline(Discipline $discipline): bool|string
    {
        if ($discipline->matches()->exists()) {
            return 'No se puede eliminar: la disciplina tiene partidos registrados. Desactívala en su lugar.';
        }

        try {
            $discipline->delete();
        } catch (QueryException) {
            return 'No se puede eliminar: la disciplina tiene historial asociado. Desactívala en su lugar.';
        }

        return true;
    }
}
