<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeasonStatus;
use App\Enums\SeasonTeamPlayerStatus;
use App\Models\SeasonTeamPlayer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Carnets / fotochecks de jugador. Diseno tomado del proyecto de
 * referencia (pichanga), pero el carnet es de una INSCRIPCION
 * (season_team_player), no de un Player suelto: Player es un catalogo
 * global y el mismo jugador puede estar en temporadas distintas, cada una
 * con su equipo, torneo y organizador.
 */
class PlayerCardService
{
    public function __construct(private PdfImageService $images)
    {
    }

    /** Relaciones que prepareCardData() necesita (para cargarlas en lote). */
    public const RELATIONS = [
        'player',
        'seasonTeam.team',
        'seasonTeam.season.tournament.organizer',
    ];

    /**
     * Datos de un carnet para las vistas reports.pdf.jugador-carnet(-multiple).
     *
     * @return array<string, mixed>
     */
    public function prepareCardData(SeasonTeamPlayer $entry): array
    {
        $entry->loadMissing(self::RELATIONS);

        $team = $entry->seasonTeam->team;
        $season = $entry->seasonTeam->season;
        $tournament = $season->tournament;
        $organizer = $tournament->organizer;

        return [
            'entry' => $entry,
            'player' => $entry->player,
            'team' => $team,
            'season' => $season,
            'tournament' => $tournament,
            'organizer' => $organizer,
            'qrCode' => $this->qrDataUri($this->verificationUrl($entry)),
            'issueDate' => now()->format('d/m/Y'),
            'cardNumber' => $this->cardNumber($entry),
            'playerPhotoBase64' => $this->images->toBase64($entry->player->photo_path),
            'teamLogoBase64' => $this->images->toBase64($team->logo_path),
            // Ni User ni Tournament tienen campo de firma (ver commit de setup):
            // la vista cae a su fallback (solo el nombre del organizador).
            'organizerSignatureBase64' => null,
        ];
    }

    /**
     * Mismo formato que PlayerCardController::generateCardNumber() de la
     * referencia: CP-<torneo>-<equipo>-<jugador>, cada tramo con 3 digitos.
     */
    public function cardNumber(SeasonTeamPlayer $entry): string
    {
        $pad = fn (int $id) => str_pad((string) $id, 3, '0', STR_PAD_LEFT);

        return sprintf(
            'CP-%s-%s-%s',
            $pad($entry->seasonTeam->season->tournament_id),
            $pad($entry->seasonTeam->team_id),
            $pad($entry->player_id),
        );
    }

    /**
     * URL publica del QR. Firmada: la referencia usa /player/verify/{id}
     * sin firma, lo que permite recorrer ids y listar jugadores (a veces
     * menores de edad). Con la firma solo abre quien tiene el carnet.
     */
    public function verificationUrl(SeasonTeamPlayer $entry): string
    {
        return URL::signedRoute('cards.verify', ['seasonTeamPlayer' => $entry->id]);
    }

    /**
     * Datos de la pagina publica de verificacion (a la que apunta el QR).
     * Solo lo necesario para cotejar el carnet: nombre, foto, equipo,
     * temporada, dorsal y estado. Nada de documento completo, fecha de
     * nacimiento ni edad (la referencia los mostraba).
     *
     * Vigente = ficha Habilitada, inscripcion activa, jugador activo y
     * temporada no cerrada.
     *
     * @return array<string, mixed>
     */
    public function verificationData(SeasonTeamPlayer $entry): array
    {
        $entry->loadMissing(self::RELATIONS);

        $seasonTeam = $entry->seasonTeam;
        $season = $seasonTeam->season;
        $player = $entry->player;

        $valid = $entry->status === SeasonTeamPlayerStatus::Active
            && $seasonTeam->is_active
            && $player->is_active
            && $season->status !== SeasonStatus::Closed;

        $statusLabel = match (true) {
            $season->status === SeasonStatus::Closed => 'Temporada cerrada',
            ! $seasonTeam->is_active || ! $player->is_active => 'Inscripción inactiva',
            default => $entry->status->label(),
        };

        $dni = (string) $player->dni;
        $public = Storage::disk('public');

        return [
            'entry' => $entry,
            'player' => $player,
            'team' => $seasonTeam->team,
            'season' => $season,
            'tournament' => $season->tournament,
            'valid' => $valid,
            'statusLabel' => $statusLabel,
            'maskedDocument' => $dni === '' ? '—' : str_repeat('•', max(0, mb_strlen($dni) - 3)) . mb_substr($dni, -3),
            'cardNumber' => $this->cardNumber($entry),
            'photoUrl' => $player->photo_path ? $public->url($player->photo_path) : null,
            'teamLogoUrl' => $seasonTeam->team->logo_path ? $public->url($seasonTeam->team->logo_path) : null,
        ];
    }

    /** QR en SVG como data URI base64 ('' si falla: la vista muestra "QR no disp."). */
    private function qrDataUri(string $url): string
    {
        try {
            $writer = new Writer(new ImageRenderer(new RendererStyle(160, 1), new SvgImageBackEnd()));

            return 'data:image/svg+xml;base64,' . base64_encode($writer->writeString($url));
        } catch (Throwable $e) {
            Log::error('[PlayerCardService] Error generando QR: ' . $e->getMessage());

            return '';
        }
    }
}
