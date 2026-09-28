<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\SeasonTeam;
use App\Services\RosterReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF del Padron de Jugadores; los datos los arma RosterReportService.
 * Autorizacion (los parametros vienen de la URL, se revalidan aqui):
 *   - Un equipo (season_team_id): SeasonTeamPolicy::printRoster, organizador
 *     del torneo o delegado EFECTIVO del equipo. Un season_team_id ajeno da
 *     403 antes de mirar la temporada.
 *   - Todos los equipos (con o sin categoria): SeasonPolicy::printReports,
 *     admin u organizador del torneo; nunca el delegado.
 */
class RosterReportController extends Controller
{
    public function pdf(Request $request, RosterReportService $service): Response
    {
        $validated = $request->validate([
            'season_id' => ['required', 'integer'],
            'season_team_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $season = Season::with('tournament')->findOrFail($validated['season_id']);
        $seasonTeamId = isset($validated['season_team_id']) ? (int) $validated['season_team_id'] : null;
        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;

        if ($seasonTeamId !== null) {
            $seasonTeam = SeasonTeam::findOrFail($seasonTeamId);
            Gate::authorize('printRoster', $seasonTeam);
            // Que sea de esa temporada lo valida RosterReportService (404).
        } else {
            Gate::authorize('printReports', $season);
        }

        return $service->pdf($season->id, $seasonTeamId, $categoryId)->stream($service->filename($season->id));
    }
}
