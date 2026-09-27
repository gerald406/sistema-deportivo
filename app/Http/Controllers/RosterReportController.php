<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Season;
use App\Services\RosterReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF del Padron de Jugadores. La autorizacion es SeasonPolicy::printReports
 * (admin u organizador del torneo); los datos los arma RosterReportService.
 */
class RosterReportController extends Controller
{
    public function pdf(Request $request, RosterReportService $service): Response
    {
        $validated = $request->validate([
            'season_id' => ['required', 'integer'],
            'season_team_id' => ['nullable', 'integer'],
        ]);

        $season = Season::with('tournament')->findOrFail($validated['season_id']);
        Gate::authorize('printReports', $season);

        $seasonTeamId = isset($validated['season_team_id']) ? (int) $validated['season_team_id'] : null;

        return $service->pdf($season->id, $seasonTeamId)->stream($service->filename($season->id));
    }
}
