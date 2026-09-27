<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesRosterEntries;
use App\Models\SeasonTeamPlayer;
use App\Services\PlayerCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Carnets (fotochecks) en PDF. Recibe ids de season_team_player (una
 * inscripcion), nunca de Player. Autoriza CADA carnet con
 * SeasonTeamPlayerPolicy::printCard; los datos los arma PlayerCardService.
 */
class PlayerCardController extends Controller
{
    use AuthorizesRosterEntries;

    public function __construct(private PlayerCardService $cards)
    {
    }

    public function single(SeasonTeamPlayer $seasonTeamPlayer): Response
    {
        $seasonTeamPlayer->load(PlayerCardService::RELATIONS);
        Gate::authorize('printCard', $seasonTeamPlayer);

        return Pdf::loadView('reports.pdf.jugador-carnet', $this->cards->prepareCardData($seasonTeamPlayer))
            ->setPaper('a4', 'portrait')
            ->stream("fotocheck_{$seasonTeamPlayer->player->dni}.pdf");
    }

    /**
     * Pagina PUBLICA (sin login) a la que apunta el QR. La ruta exige firma
     * (middleware 'signed'): sin ella no se puede recorrer ids.
     */
    public function verify(SeasonTeamPlayer $seasonTeamPlayer): View
    {
        return view('public.card-verify', $this->cards->verificationData($seasonTeamPlayer));
    }

    /** GET ?ids=1,2,3 */
    public function massive(Request $request): Response
    {
        return $this->massivePdf($request->query('ids', ''));
    }

    /** POST ids=1,2,3 (evita URLs largas; lo usa el modo carnets de Planteles). */
    public function massivePost(Request $request): Response
    {
        return $this->massivePdf($request->input('ids', ''));
    }

    private function massivePdf(mixed $rawIds): Response
    {
        $entries = $this->authorizedEntries($rawIds, PlayerCardService::RELATIONS);

        $cards = $entries->map(fn (SeasonTeamPlayer $e) => $this->cards->prepareCardData($e))->all();

        return Pdf::loadView('reports.pdf.jugador-carnet-multiple', ['cards' => $cards])
            ->setPaper('a4', 'portrait')
            ->stream('fotochecks_masivos.pdf');
    }
}
