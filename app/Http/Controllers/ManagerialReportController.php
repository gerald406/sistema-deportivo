<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Season;
use App\Services\ManagerialReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF del Reporte Gerencial. Autorizacion: SeasonPolicy::printReports
 * (admin u organizador del torneo); datos: ManagerialReportService.
 */
class ManagerialReportController extends Controller
{
    public function pdf(Request $request, ManagerialReportService $service): Response
    {
        $validated = $request->validate([
            'season_id' => ['required', 'integer'],
        ]);

        $season = Season::with('tournament')->findOrFail($validated['season_id']);
        Gate::authorize('printReports', $season);

        return $service->pdf($season->id)->stream($service->filename($season->id));
    }
}
