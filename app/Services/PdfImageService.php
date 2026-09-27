<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Imagenes para los PDFs de DomPDF (carnets, padron, ficha de inscripcion,
 * reporte gerencial). DomPDF no resuelve bien URLs http del propio sitio,
 * asi que las imagenes del disco public se incrustan como data URI base64.
 *
 * Es el helper imageToBase64() del proyecto de referencia (mismo
 * comportamiento: valida file_exists, loggea warning si falta, captura
 * excepciones), pero en UN solo lugar en vez de copiado en cada Controller.
 */
class PdfImageService
{
    /**
     * @param string|null $storagePath ruta relativa al disco public (p. ej. "players/abc.png")
     * @return string|null data URI, o null si no hay imagen (el PDF muestra su fallback)
     */
    public function toBase64(?string $storagePath): ?string
    {
        if (empty($storagePath)) {
            return null;
        }

        $absolutePath = storage_path('app/public/' . $storagePath);

        if (! file_exists($absolutePath)) {
            Log::warning("[PdfImageService] Imagen no encontrada: {$absolutePath}");

            return null;
        }

        try {
            $mime = mime_content_type($absolutePath) ?: 'image/jpeg';
            $content = file_get_contents($absolutePath);

            return 'data:' . $mime . ';base64,' . base64_encode($content);
        } catch (Throwable $e) {
            Log::error("[PdfImageService] Error al leer imagen: {$e->getMessage()}");

            return null;
        }
    }
}
