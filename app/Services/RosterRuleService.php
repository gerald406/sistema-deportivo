<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Gender;
use App\Enums\SeasonTeamPlayerStatus;
use App\Models\Player;
use App\Models\Season;
use App\Models\SeasonTeamPlayer;
use App\Models\Team;

/**
 * Reglas de reglamento del plantel. Son DATOS de la competencia (su nivel,
 * su genero y la fila del cuadro de puntaje), no codigo de un reglamento:
 * un campo vacio significa "sin restriccion".
 *
 *   R1  Genero: la competencia de damas o varones solo admite ese genero.
 *   R2  Edad por ANIO de nacimiento (D10): anio de la olimpiada - anio de
 *       nacimiento >= edad minima del nivel. No hay edad maxima: se puede
 *       jugar en un nivel menor.
 *   R3  Tope de plantel de la fila del cuadro (max_players).
 *   R4  Un participante, una sola delegacion en toda la olimpiada.
 *
 * R1 y R2 BLOQUEAN siempre (D26): los datos (nivel y genero de la
 * competencia) siguen siendo configurables, pero quien no los cumple no
 * entra. R3 y R4 advierten por defecto (D11) y solo bloquean si la fila del
 * cuadro esta marcada como bloqueante. evaluate() ya hace esa
 * clasificacion: quien llama rechaza si hay `errors` y muestra `warnings`.
 * El minimo de participantes nunca bloquea (ver missingForMinimum()).
 */
class RosterRuleService
{
    /**
     * Sirve igual antes de inscribir (¿se puede?) y despues (¿sigue en
     * regla?): R3 y R4 no cuentan la ficha del propio participante.
     *
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function evaluate(Season $season, Team $team, Player $player): array
    {
        $always = $this->filled([$this->genderViolation($season, $player), $this->ageViolation($season, $player)]);
        $byRow = $this->filled([$this->rosterLimitViolation($season, $team, $player), $this->otherDelegationViolation($season, $team, $player)]);

        return $this->isBlocking($season)
            ? ['errors' => [...$always, ...$byRow], 'warnings' => []]
            : ['errors' => $always, 'warnings' => $byRow];
    }

    /**
     * Todas las reglas incumplidas, sin clasificar.
     *
     * @return list<string>
     */
    public function violations(Season $season, Team $team, Player $player): array
    {
        return array_values(array_filter([
            $this->genderViolation($season, $player),
            $this->ageViolation($season, $player),
            $this->rosterLimitViolation($season, $team, $player),
            $this->otherDelegationViolation($season, $team, $player),
        ]));
    }

    /** La fila del cuadro de la competencia pide que R3 y R4 bloqueen (R1 y R2 bloquean siempre). */
    public function isBlocking(Season $season): bool
    {
        return (bool) $season->event?->rules_blocking;
    }

    /** Anio contra el que se mide la edad: el de la olimpiada. */
    public function referenceYear(Season $season): int
    {
        return (int) ($season->gamesEdition?->start_date?->year ?? now()->year);
    }

    /** Cuantos participantes habilitados faltan para el minimo de la fila (0 = cumple o no hay minimo). */
    public function missingForMinimum(Season $season, int $activeCount): int
    {
        $min = $season->event?->min_players;

        return $min === null ? 0 : max(0, $min - $activeCount);
    }

    /** @return list<string> */
    private function filled(array $violations): array
    {
        return array_values(array_filter($violations));
    }

    private function genderViolation(Season $season, Player $player): ?string
    {
        $required = $season->gender;

        if ($required === null || $required === Gender::Mixed) {
            return null;
        }

        if ($player->gender === $required) {
            return null;
        }

        $actual = $player->gender ? mb_strtolower($player->gender->label()) : 'sin género registrado';

        return "La competencia es de {$required->label()} y el participante figura como {$actual}.";
    }

    private function ageViolation(Season $season, Player $player): ?string
    {
        $level = $season->category;

        if ($level === null || $level->min_age === null) {
            return null;
        }

        if ($player->birth_date === null) {
            return "El participante no tiene fecha de nacimiento: no se puede comprobar la edad de {$level->name} ({$level->min_age}).";
        }

        $year = $this->referenceYear($season);
        $age = $year - $player->birth_date->year;

        return $age >= $level->min_age
            ? null
            : "Nacido en {$player->birth_date->year}: tendría {$age} en {$year}; {$level->name} exige {$level->min_age}.";
    }

    private function rosterLimitViolation(Season $season, Team $team, Player $player): ?string
    {
        $max = $season->event?->max_players;

        if ($max === null) {
            return null;
        }

        $others = SeasonTeamPlayer::query()
            ->where('player_id', '!=', $player->id)
            ->where('status', '!=', SeasonTeamPlayerStatus::Inactive->value)
            ->whereHas('seasonTeam', fn ($q) => $q->where('season_id', $season->id)->where('team_id', $team->id))
            ->count();

        return $others >= $max ? "El plantel ya tiene {$others} de {$max} permitidos." : null;
    }

    private function otherDelegationViolation(Season $season, Team $team, Player $player): ?string
    {
        $other = SeasonTeamPlayer::query()
            ->where('player_id', $player->id)
            ->whereHas('seasonTeam', fn ($q) => $q
                ->where('team_id', '!=', $team->id)
                ->whereHas('season', fn ($s) => $s->where('games_edition_id', $season->games_edition_id)))
            ->with('seasonTeam.team:id,name')
            ->first();

        return $other ? "Ya está inscrito con {$other->seasonTeam->team->name} en esta olimpiada." : null;
    }
}
