<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SeasonTeamPlayer;
use App\Services\PlayerCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Carnets (fotochecks) en PDF. Recibe ids de season_team_player (una
 * inscripcion), nunca de Player. Autoriza CADA carnet con
 * SeasonTeamPlayerPolicy::printCard; los datos los arma PlayerCardService.
 */
class PlayerCardController extends Controller
{
    /** Tope por PDF: DomPDF con cientos de imagenes base64 agota memoria. */
    private const MAX_CARDS = 150;

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
        $entries = $this->authorizedEntries($rawIds);

        $cards = $entries->map(fn (SeasonTeamPlayer $e) => $this->cards->prepareCardData($e))->all();

        return Pdf::loadView('reports.pdf.jugador-carnet-multiple', ['cards' => $cards])
            ->setPaper('a4', 'portrait')
            ->stream('fotochecks_masivos.pdf');
    }

    /**
     * Ids "1,2,3" (o array) -> inscripciones, cada una autorizada. Si
     * alguna no es del usuario, 403 para todo el lote (como la referencia),
     * sin revelar los datos de las demas.
     */
    private function authorizedEntries(mixed $rawIds): Collection
    {
        $ids = collect(is_array($rawIds) ? $rawIds : explode(',', (string) $rawIds))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        abort_if($ids->isEmpty(), 422, 'Selecciona al menos un jugador.');
        abort_if($ids->count() > self::MAX_CARDS, 422, 'Máximo ' . self::MAX_CARDS . ' carnets por PDF.');

        $entries = SeasonTeamPlayer::with(PlayerCardService::RELATIONS)
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (SeasonTeamPlayer $e) => [$e->seasonTeam->team->name, $e->shirt_number ?? 999])
            ->values();

        abort_if($entries->count() !== $ids->count(), 404, 'Alguno de los jugadores no existe.');

        $entries->each(fn (SeasonTeamPlayer $e) => Gate::authorize('printCard', $e));

        return $entries;
    }
}
