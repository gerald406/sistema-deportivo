<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EditionTeamStatus;
use App\Enums\SeasonTeamPlayerStatus;
use App\Models\GamesEdition;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Team;
use App\Models\User;
use App\Policies\SeasonTeamPlayerPolicy;
use Illuminate\Support\Collection;

/**
 * Ficha de inscripcion GENERAL (D21): una por delegacion y disciplina
 * (season_team), con la lista de su plantel. Las bases piden "Fichas de
 * inscripcion general por delegacion ... por duplicado para cada
 * disciplina" (VII.c) y una declaracion jurada de la Direccion (VII.d):
 * van juntas. D30: la lista lleva solo N.o, apellidos y nombres, DNI
 * enmascarado y edad; la Direccion otorga representacion al delegado y
 * firma solo el delegado.
 *
 * Quien la imprime: la misma regla que las fichas individuales y los
 * carnets (SeasonTeamPlayerPolicy::managesSeasonTeam): el admin, el
 * organizador de la olimpiada y los delegados de la delegacion.
 */
class GeneralRegistrationFormService
{
    /** Copias por ficha (bases VII.c: "por duplicado"). */
    public const COPIES = ['ORIGINAL', 'COPIA'];

    public function __construct(private PdfImageService $images)
    {
    }

    public function canPrint(User $user, SeasonTeam $seasonTeam): bool
    {
        return SeasonTeamPlayerPolicy::managesSeasonTeam($user, $seasonTeam);
    }

    /**
     * Inscripciones activas de una delegacion en una olimpiada, con plantel,
     * en el orden del cuadro de puntaje: para "todas las fichas generales".
     *
     * @return Collection<int, SeasonTeam>
     */
    public function seasonTeamsOf(GamesEdition $edition, Team $team): Collection
    {
        return SeasonTeam::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->whereHas('season', fn ($q) => $q->where('games_edition_id', $edition->id)->where('is_active', true))
            ->whereHas('roster', fn ($q) => $q->where('status', '!=', SeasonTeamPlayerStatus::Inactive->value))
            // games_edition_id: lo necesita canPrint() (SeasonTeam::hasDelegate()).
            ->with('season:id,name,games_edition_id')
            ->get()
            ->sortBy(fn (SeasonTeam $st) => $st->season->name)
            ->values();
    }

    /**
     * Datos de una ficha. Participantes: el plantel sin los dados de baja,
     * por apellidos, con solo lo que lleva la lista (D30). Edad = ano de la
     * olimpiada - ano de nacimiento (regla 7 de CLAUDE.md), la misma que
     * valida RosterRuleService. El DNI va con sus ultimos digitos ocultos
     * (maskDni()): la ficha circula impresa.
     *
     * @return array<string, mixed>
     */
    public function prepare(SeasonTeam $seasonTeam): array
    {
        // load() y no loadMissing(): quien llama puede traer la competencia con columnas parciales.
        $seasonTeam->load([
            'team', 'season.sport', 'season.discipline', 'season.category', 'season.gamesEdition.organizer',
        ]);

        $edition = $seasonTeam->season->gamesEdition;
        $year = (int) ($edition->start_date?->format('Y') ?? now()->format('Y'));
        $editionEntry = $seasonTeam->team->editionTeams()->where('games_edition_id', $edition->id)->with('delegate:id,name', 'substituteDelegate:id,name')->first();

        $participants = $seasonTeam->roster()
            ->where('status', '!=', SeasonTeamPlayerStatus::Inactive->value)
            ->with('player')
            ->get()
            ->sortBy(fn (SeasonTeamPlayer $e) => [$e->player->last_name, $e->player->first_name])
            ->values()
            ->map(fn (SeasonTeamPlayer $e, int $i) => [
                'number' => $i + 1,
                'name' => mb_strtoupper($e->player->last_name.', '.$e->player->first_name),
                'dni' => $this->maskDni($e->player->dni),
                'age' => $e->player->birth_date ? $year - (int) $e->player->birth_date->format('Y') : null,
            ]);

        return [
            'edition' => $edition,
            'season' => $seasonTeam->season,
            'team' => $seasonTeam->team,
            'participants' => $participants,
            'participantsInWords' => $this->countInWords($participants->count()),
            'delegateName' => $editionEntry?->delegate?->name,
            'substituteName' => $editionEntry?->substituteDelegate?->name,
            'registered' => $editionEntry?->status === EditionTeamStatus::Registered,
            'issueDate' => now()->format('d/m/Y'),
            'editionLogo' => $this->images->toBase64($edition->logo),
            'teamLogo' => $this->images->toBase64($seasonTeam->team->logo_path),
        ];
    }

    /** Primeros 4 digitos visibles y el resto con asteriscos: 45531234 -> 4553****. */
    public function maskDni(?string $dni): string
    {
        $dni = trim((string) $dni);

        return $dni === '' ? '—' : mb_substr($dni, 0, 4).str_repeat('*', max(0, mb_strlen($dni) - 4));
    }

    /**
     * Cantidad en letras y cifras para la declaracion jurada, en masculino
     * ante "participantes": 6 -> "seis (6)", 21 -> "veintiún (21)". Sin
     * depender de la extension intl del servidor.
     */
    public function countInWords(int $n): string
    {
        return ($n >= 0 && $n < 1000 ? $this->spanishNumber($n) : (string) $n)." ({$n})";
    }

    private function spanishNumber(int $n): string
    {
        $units = ['cero', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
            'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
            'veinte', 'veintiún', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
        $tens = [3 => 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $hundreds = [1 => 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        if ($n < 30) {
            return $units[$n];
        }

        if ($n < 100) {
            return $tens[intdiv($n, 10)].($n % 10 ? ' y '.$units[$n % 10] : '');
        }

        if ($n === 100) {
            return 'cien';
        }

        return $hundreds[intdiv($n, 100)].($n % 100 ? ' '.$this->spanishNumber($n % 100) : '');
    }
}
