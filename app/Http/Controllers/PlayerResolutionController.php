<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\PlayerRegistrationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de la resolucion (PDF) de un participante (D20). El archivo esta
 * en el disco privado: esta ruta es la UNICA forma de leerlo y autoriza
 * cada descarga con PlayerPolicy::view (su delegacion, el organizador de la
 * olimpiada y el admin).
 */
class PlayerResolutionController extends Controller
{
    public function show(Player $player): StreamedResponse
    {
        Gate::authorize('view', $player);

        $disk = Storage::disk(PlayerRegistrationService::RESOLUTION_DISK);
        abort_unless($player->resolution_path && $disk->exists($player->resolution_path), 404);

        return $disk->response($player->resolution_path, "resolucion_{$player->dni}.pdf", [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
