<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SeasonTeamPlayer;

/**
 * Datos de la Ficha de Inscripcion (reports.pdf.jugador-inscripcion).
 * Equivale a PlayerRegistrationController::prepareRegistrationData() de la
 * referencia, pero fuera del Controller y sobre la inscripcion
 * (season_team_player): equipo, temporada, torneo, organizador y delegado
 * EFECTIVO de esa temporada. Reusa el numero de carnet de PlayerCardService
 * y las imagenes de PdfImageService (el helper imageToBase64 compartido).
 */
class RegistrationFormService
{
    public const RELATIONS = [
        'player',
        'seasonTeam.team.delegate',
        'seasonTeam.delegate',
        'seasonTeam.season.sport',
        'seasonTeam.season.tournament.organizer',
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
        $tournament = $seasonTeam->season->tournament;

        return [
            'entry' => $entry,
            'player' => $entry->player,
            'team' => $seasonTeam->team,
            'season' => $seasonTeam->season,
            'tournament' => $tournament,
            'organizer' => $tournament->organizer,
            'delegateName' => $seasonTeam->effectiveDelegate()?->name ?? '',
            // User no tiene documento de identidad: la vista muestra "Sin registrar".
            'delegateDni' => '',
            'issueDate' => now()->format('d/m/Y'),
            'cardNumber' => $this->cards->cardNumber($entry),
            'playerPhotoBase64' => $this->images->toBase64($entry->player->photo_path),
            'teamLogoBase64' => $this->images->toBase64($seasonTeam->team->logo_path),
            'tournamentLogoBase64' => $this->images->toBase64($tournament->logo),
            'organizerSignatureBase64' => null,
        ];
    }
}
