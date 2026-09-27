<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Season;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Reporte Gerencial de una temporada. Equivalente a
 * pichanga ReportService::generateManagerialPdf() sin ramas por deporte.
 *
 * Que secciones se muestran y sus datos salen de SeasonOverviewService, el
 * MISMO criterio que la zona publica de resultados (ver su docblock): asi
 * el PDF y la web nunca discrepan.
 *
 * No autoriza: ManagerialReportController lo hace con
 * SeasonPolicy::printReports antes de llamar aqui.
 */
class ManagerialReportService
{
    public function __construct(private SeasonOverviewService $overview)
    {
    }

    /**
     * @return array<string, mixed> datos para reports.pdf.managerial
     */
    public function collect(int $seasonId): array
    {
        $season = Season::with('tournament.organizer', 'sport')->findOrFail($seasonId);

        $sections = $this->overview->sections($season);
        $lastMatchday = $this->overview->completedMatchdays($season)->first();
        $nextMatchday = $this->overview->nextMatchday($season);
        $standings = $sections['standings'] ? $this->overview->standings($season) : collect();

        return [
            'season' => $season,
            'tournament' => $season->tournament,
            'organizer' => $season->tournament->organizer,
            'scoring' => $season->scoringConfigs()->orderBy('id')->first(),
            'generatedAt' => now()->format('d/m/Y H:i'),
            'lastMatchday' => $lastMatchday,
            'lastResults' => $lastMatchday ? $this->overview->matchRows($lastMatchday, onlyDecided: true) : collect(),
            'nextMatchday' => $nextMatchday,
            'nextGames' => $nextMatchday ? $this->overview->matchRows($nextMatchday, onlyDecided: false) : collect(),
            'showStandings' => $sections['standings'],
            'standings' => $standings,
            'standingColumns' => $this->overview->standingColumns($season, $standings),
            'showScorers' => $sections['scorers'],
            'topScorers' => $sections['scorers'] ? $this->overview->topScorers($season, 20) : collect(),
            'showSuspensions' => $sections['suspensions'],
            'suspensions' => $sections['suspensions'] ? $this->overview->activeSuspensions($season) : collect(),
            'showYellows' => $sections['yellows'],
            'yellowCardRisks' => $sections['yellows'] ? $this->overview->playersWithTwoYellows($season) : collect(),
        ];
    }

    public function pdf(int $seasonId): DomPdf
    {
        return Pdf::loadView('reports.pdf.managerial', $this->collect($seasonId))->setPaper('A4', 'landscape');
    }

    public function filename(int $seasonId): string
    {
        $season = Season::with('tournament:id,name')->findOrFail($seasonId);

        return 'Reporte_Gerencial_' . str($season->tournament->name . ' ' . $season->name)->slug('_') . '.pdf';
    }
}
