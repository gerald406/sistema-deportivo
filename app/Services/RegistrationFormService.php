<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SeasonTeamPlayer;

/**
 * Datos de la Ficha de Inscripcion (reports.pdf.jugador-inscripcion).
 * Equivale a PlayerRegistrationController::prepareRegistrationData() de la
 * referencia, pero fuera del Controller y sobre la inscripcion
 * (season_team_player): equipo, competencia, olimpiada, organizador y delegado
 * EFECTIVO de esa competencia. Reusa el numero de carnet de PlayerCardService
 * y las imagenes de PdfImageService (el helper imageToBase64 compartido).
 */
class RegistrationFormService
{
    public const RELATIONS = [
        'player',
        'seasonTeam.team',
        'seasonTeam.season.sport',
        'seasonTeam.season.gamesEdition.organizer',
    ];

    public function __construct(
        private PdfImageService $images,
        private PlayerCardService $cards,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function prepare(SeasonTeamPlayer $entry): array
    {
        $entry->loadMissing(self::RELATIONS);

        $seasonTeam = $entry->seasonTeam;
        $gamesEdition = $seasonTeam->season->gamesEdition;

        return [
            'entry' => $entry,
            'player' => $entry->player,
            'team' => $seasonTeam->team,
            'season' => $seasonTeam->season,
            'gamesEdition' => $gamesEdition,
            'organizer' => $gamesEdition->organizer,
            // Firma el titular; si no hay, el suplente.
            'delegateName' => $seasonTeam->delegates()->first()?->name ?? '',
            // User no tiene documento de identidad: la vista muestra "Sin registrar".
            'delegateDni' => '',
            'issueDate' => now()->format('d/m/Y'),
            'cardNumber' => $this->cards->cardNumber($entry),
            'playerPhotoBase64' => $this->images->toBase64($entry->player->photo_path),
            'teamLogoBase64' => $this->images->toBase64($seasonTeam->team->logo_path),
            'gamesEditionLogoBase64' => $this->images->toBase64($gamesEdition->logo),
            'organizerSignatureBase64' => null,
        ];
    }
}
