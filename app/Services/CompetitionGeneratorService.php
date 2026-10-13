<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Gender;
use App\Enums\SeasonStatus;
use App\Models\Discipline;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\Season;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Genera las competencias (Season) de una olimpiada a partir de su cuadro
 * de puntaje. Por cada fila activa con deporte crea una competencia por
 * genero (GamesEditionEvent::genders()) y, si la fila es "por prueba", por
 * cada prueba activa del deporte.
 *
 * Es idempotente y solo AGREGA: la identidad de una competencia generada es
 * fila + genero + prueba, asi que volver a generar no duplica, y nunca
 * modifica, reactiva ni borra una competencia existente. Las que no
 * correspondan (p. ej. una prueba que las bases no ofrecen en un nivel) se
 * desactivan a mano; si se borraran, el generador las volveria a crear.
 */
class CompetitionGeneratorService
{
    public const DEFAULT_PHASE = 'Fase única';

    public function __construct(private GamesEditionService $editions)
    {
    }

    /**
     * @return array{created: int, existing: int, skipped: int} skipped = filas activas sin deporte
     */
    public function generate(GamesEdition $edition, User $actor): array
    {
        if (! $this->editions->manages($actor, $edition)) {
            throw new AuthorizationException('Solo el organizador de la olimpiada o el administrador generan sus competencias.');
        }

        $result = ['created' => 0, 'existing' => 0, 'skipped' => 0];

        DB::transaction(function () use ($edition, &$result) {
            $rows = $edition->events()->where('is_active', true)->with(['sport', 'category'])->get();

            foreach ($rows as $row) {
                if ($row->sport === null) {
                    $result['skipped']++;

                    continue;
                }

                $disciplines = $row->per_discipline
                    ? Discipline::where('sport_id', $row->sport_id)->where('is_active', true)->orderBy('id')->get()->all()
                    : [null];

                foreach ($disciplines as $discipline) {
                    foreach ($row->genders() as $gender) {
                        $this->ensureCompetition($edition, $row, $gender, $discipline) ? $result['created']++ : $result['existing']++;
                    }
                }
            }
        });

        return $result;
    }

    /** true si la creo; false si ya existia. */
    private function ensureCompetition(GamesEdition $edition, GamesEditionEvent $row, ?string $gender, ?Discipline $discipline): bool
    {
        $exists = Season::where('games_edition_id', $edition->id)
            ->where('games_edition_event_id', $row->id)
            ->where(fn ($q) => $gender === null ? $q->whereNull('gender') : $q->where('gender', $gender))
            ->where(fn ($q) => $discipline === null ? $q->whereNull('discipline_id') : $q->where('discipline_id', $discipline->id))
            ->exists();

        if ($exists) {
            return false;
        }

        $season = Season::create([
            'games_edition_id' => $edition->id,
            'games_edition_event_id' => $row->id,
            'sport_id' => $row->sport_id,
            'category_id' => $row->category_id,
            'discipline_id' => $discipline?->id,
            'gender' => $gender,
            'name' => $this->availableName($edition, $this->nameFor($row, $gender, $discipline)),
            'start_date' => $edition->start_date,
            'end_date' => $edition->end_date,
            'status' => SeasonStatus::Active,
            'is_active' => true,
        ]);

        // Puntos por partido: los valores por defecto de scoring_configs.
        $season->scoringConfigs()->create(['phase_name' => self::DEFAULT_PHASE]);

        return true;
    }

    /** "{Deporte}[ — {Prueba}] {Nivel} {Damas|Varones|Mixto}", sin la parte que no aplique. */
    private function nameFor(GamesEditionEvent $row, ?string $gender, ?Discipline $discipline): string
    {
        return implode(' ', array_filter([
            $row->sport->name.($discipline ? " — {$discipline->name}" : ''),
            $row->category?->name,
            $gender !== null ? Gender::from($gender)->label() : null,
        ]));
    }

    /**
     * seasons tiene unique(games_edition_id, name): si una competencia
     * creada a mano ya usa el nombre, se numera en vez de fallar.
     */
    private function availableName(GamesEdition $edition, string $name): string
    {
        $name = mb_substr($name, 0, 95);
        $candidate = $name;

        for ($n = 2; Season::where('games_edition_id', $edition->id)->where('name', $candidate)->exists(); $n++) {
            $candidate = "{$name} ({$n})";
        }

        return $candidate;
    }
}
