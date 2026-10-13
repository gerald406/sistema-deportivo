<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SeasonTeamPlayerStatus;
use App\Http\Controllers\Concerns\AuthorizesRosterEntries;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Services\RegistrationFormService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fichas de Inscripcion en PDF (una hoja A4 por participante). Misma
 * autorizacion que los carnets (SeasonTeamPlayerPolicy::printCard, sin
 * reescribirla); los datos los arma RegistrationFormService.
 */
class PlayerRegistrationController extends Controller
{
    use AuthorizesRosterEntries;

    public function __construct(private RegistrationFormService $forms)
    {
    }

    public function single(SeasonTeamPlayer $seasonTeamPlayer): Response
    {
        $seasonTeamPlayer->load(RegistrationFormService::RELATIONS);
        Gate::authorize('printCard', $seasonTeamPlayer);

        return $this->stream(collect([$seasonTeamPlayer]), "ficha_inscripcion_{$seasonTeamPlayer->player->dni}.pdf");
    }

    /** Todas las fichas del plantel de una inscripcion (sin las dadas de baja). */
    public function byTeam(SeasonTeam $seasonTeam): Response
    {
        $entries = $seasonTeam->roster()
            ->where('status', '!=', SeasonTeamPlayerStatus::Inactive->value)
            ->with(RegistrationFormService::RELATIONS)
            ->orderByRaw('shirt_number IS NULL, shirt_number')
            ->get();

        abort_if($entries->isEmpty(), 404, 'El equipo no tiene participantes activos en su plantel.');

        $entries->each(fn (SeasonTeamPlayer $e) => Gate::authorize('printCard', $e));

        $seasonTeam->loadMissing('team:id,name');

        return $this->stream($entries, 'fichas_inscripcion_' . str($seasonTeam->team->name)->slug('_') . '.pdf');
    }

    /** POST ids=1,2,3 desde el selector de fichas. */
    public function massivePost(Request $request): Response
    {
        $entries = $this->authorizedEntries($request->input('ids', ''), RegistrationFormService::RELATIONS);

        return $this->stream($entries, 'fichas_inscripcion_seleccion.pdf');
    }

    /** @param Collection<int, SeasonTeamPlayer> $entries */
    private function stream(Collection $entries, string $filename): Response
    {
        $cards = $entries->map(fn (SeasonTeamPlayer $e) => $this->forms->prepare($e))->all();

        return Pdf::loadView('reports.pdf.jugador-inscripcion', ['cards' => $cards])
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }
}
