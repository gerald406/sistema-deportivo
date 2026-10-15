<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GamesEdition;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Services\GeneralRegistrationFormService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fichas de inscripcion GENERALES en PDF (D21): una por delegacion y
 * disciplina, por duplicado. Autoriza CADA ficha con
 * GeneralRegistrationFormService::canPrint() (admin, organizador de la
 * olimpiada y delegados de la delegacion); los datos los arma el Service.
 */
class GeneralRegistrationFormController extends Controller
{
    public function __construct(private GeneralRegistrationFormService $forms)
    {
    }

    /** Ficha de una disciplina. */
    public function single(SeasonTeam $seasonTeam): Response
    {
        abort_unless($this->forms->canPrint(auth()->user(), $seasonTeam), 403);

        $form = $this->forms->prepare($seasonTeam);
        abort_if($form['participants']->isEmpty(), 404, 'La delegación aún no tiene participantes en esta disciplina.');

        return $this->stream(collect([$form]), 'ficha_general_'.str($form['team']->name.' '.$form['season']->name)->slug('_').'.pdf');
    }

    /** Todas las fichas de una delegacion en la olimpiada, en un solo PDF. */
    public function byDelegation(GamesEdition $gamesEdition, Team $team): Response
    {
        $seasonTeams = $this->forms->seasonTeamsOf($gamesEdition, $team);
        abort_if($seasonTeams->isEmpty(), 404, 'La delegación aún no tiene participantes inscritos en ninguna disciplina.');

        // Si una no corresponde, 403 para todo el lote.
        $seasonTeams->each(fn (SeasonTeam $st) => abort_unless($this->forms->canPrint(auth()->user(), $st), 403));

        return $this->stream(
            $seasonTeams->map(fn (SeasonTeam $st) => $this->forms->prepare($st)),
            'fichas_generales_'.str($team->name)->slug('_').'.pdf',
        );
    }

    /** @param Collection<int, array<string, mixed>> $forms */
    private function stream(Collection $forms, string $filename): Response
    {
        return Pdf::loadView('reports.pdf.ficha-general', ['forms' => $forms])
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }
}
