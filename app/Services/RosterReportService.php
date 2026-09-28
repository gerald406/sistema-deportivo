<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeasonTeamPlayerStatus;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;

/**
 * Padron de Jugadores de una temporada. Equivalente a
 * pichanga ReportService::generateRosterPdf() sin la rama football/volley
 * (un solo modelo para todos los deportes) y leyendo el plantel de la
 * inscripcion (season_team_player), no Team->players: el dorsal, el
 * capitan y el estado son POR TEMPORADA.
 *
 * No autoriza: RosterReportController lo hace (SeasonTeamPolicy::printRoster
 * para un equipo, SeasonPolicy::printReports para todos) antes de llamar aqui.
 */
class RosterReportService
{
    public function __construct(private PdfImageService $images)
    {
    }

    /**
     * @return array<string, mixed> datos para reports.pdf.player-roster
     */
    public function collect(int $seasonId, ?int $seasonTeamId = null, ?int $categoryId = null): array
    {
        $season = Season::with('tournament.organizer')->findOrFail($seasonId);
        $tournament = $season->tournament;

        $seasonTeams = $season->seasonTeams()
            ->where('is_active', true)
            // Acotado a la temporada: un season_team_id de otra temporada da 404.
            ->when($seasonTeamId !== null, fn ($q) => $q->whereKey($seasonTeamId))
            // Categoria de la inscripcion (division por edad/genero, ej. Sub-17 Damas).
            ->when($categoryId !== null, fn ($q) => $q->where('category_id', $categoryId))
            ->with([
                'team:id,name,logo_path,delegate_id', 'team.delegate:id,name', 'delegate:id,name',
                'roster.player:id,dni,first_name,last_name,birth_date',
            ])
            ->get()
            ->sortBy('team.name')
            ->values();

        if ($seasonTeamId !== null && $seasonTeams->isEmpty()) {
            abort(404, 'El equipo no está inscrito en esta temporada.');
        }

        if ($categoryId !== null && $seasonTeams->isEmpty()) {
            abort(404, 'No hay equipos inscritos en esa categoría en esta temporada.');
        }

        $teams = $seasonTeams->map(fn (SeasonTeam $st) => $this->teamBlock($st));

        // Header dinamico (misma logica que la referencia): con UN equipo, el
        // header fijo muestra su logo junto al del torneo (no cambia entre
        // paginas); con todos, cada .team-header del cuerpo muestra el suyo.
        $isSingleTeam = $seasonTeamId !== null;
        $single = $isSingleTeam ? $teams->first() : null;

        return [
            'tournament' => $tournament,
            'season' => $season,
            'organizer' => $tournament->organizer,
            'teams' => $teams,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'tournamentLogoBase64' => $this->images->toBase64($tournament->logo),
            // Sin campo de firma en User/Tournament (ver commit de setup).
            'organizerSignatureBase64' => null,
            'isSingleTeam' => $isSingleTeam,
            'headerTeamLogoBase64' => $single['logoBase64'] ?? null,
            'headerTeamName' => $single['team']->name ?? null,
        ];
    }

    public function pdf(int $seasonId, ?int $seasonTeamId = null, ?int $categoryId = null): DomPdf
    {
        // Igual que la referencia: un padron completo con logos puede ser pesado.
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        return Pdf::loadView('reports.pdf.player-roster', $this->collect($seasonId, $seasonTeamId, $categoryId))
            ->setPaper('A4', 'portrait');
    }

    public function filename(int $seasonId): string
    {
        $season = Season::with('tournament:id,name')->findOrFail($seasonId);

        return 'Padron_Jugadores_' . str($season->tournament->name . ' ' . $season->name)->slug('_') . '.pdf';
    }

    /**
     * @return array{seasonTeam: SeasonTeam, team: \App\Models\Team, logoBase64: ?string, delegate: ?\App\Models\User, entries: Collection<int, SeasonTeamPlayer>, activeCount: int, inactiveCount: int}
     */
    private function teamBlock(SeasonTeam $seasonTeam): array
    {
        // Por dorsal; los que no tienen dorsal al final, por apellido.
        $entries = $seasonTeam->roster
            ->sortBy([
                fn (SeasonTeamPlayer $a, SeasonTeamPlayer $b) => ($a->shirt_number ?? PHP_INT_MAX) <=> ($b->shirt_number ?? PHP_INT_MAX),
                fn (SeasonTeamPlayer $a, SeasonTeamPlayer $b) => strcmp($a->player->last_name, $b->player->last_name),
            ])
            ->values();

        $active = $entries->filter(fn (SeasonTeamPlayer $e) => $e->status === SeasonTeamPlayerStatus::Active)->count();

        return [
            'seasonTeam' => $seasonTeam,
            'team' => $seasonTeam->team,
            'logoBase64' => $this->images->toBase64($seasonTeam->team->logo_path),
            // Delegado de la temporada si existe; si no, el general del club.
            'delegate' => $seasonTeam->effectiveDelegate(),
            'entries' => $entries,
            'activeCount' => $active,
            'inactiveCount' => $entries->count() - $active,
        ];
    }
}
