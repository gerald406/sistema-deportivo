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
     * Lado mayor, en px, de las imagenes incrustadas. En un carnet la foto
     * mide ~28 mm (~330 px a 300 dpi): mandar el original (fotos de celular
     * de varios MB, logos PNG de 200 KB) solo hace que DomPDF tarde varios
     * segundos por carnet decodificandolos.
     */
    private const MAX_SIDE = 300;

    /** @var array<string, string|null> data URIs ya generados en este request (el mismo logo se repite en cada carnet) */
    private array $cache = [];

    /**
     * @param string|null $storagePath ruta relativa al disco public (p. ej. "players/abc.png")
     * @return string|null data URI, o null si no hay imagen (el PDF muestra su fallback)
     */
    public function toBase64(?string $storagePath): ?string
    {
        if (empty($storagePath)) {
            return null;
        }

        return $this->cache[$storagePath] ??= $this->encode($storagePath);
    }

    private function encode(string $storagePath): ?string
    {
        $absolutePath = storage_path('app/public/' . $storagePath);

        if (! file_exists($absolutePath)) {
            Log::warning("[PdfImageService] Imagen no encontrada: {$absolutePath}");

            return null;
        }

        try {
            $mime = mime_content_type($absolutePath) ?: 'image/jpeg';
            $content = $this->downscale($absolutePath, $mime) ?? [$mime, file_get_contents($absolutePath)];

            return 'data:' . $content[0] . ';base64,' . base64_encode($content[1]);
        } catch (Throwable $e) {
            Log::error("[PdfImageService] Error al leer imagen: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Reduce con GD si la imagen supera MAX_SIDE. PNG/WEBP/GIF quedan en PNG
     * (conservan transparencia: logos); el resto en JPEG. Devuelve null si
     * no hace falta o GD no puede leerla (se usa el original).
     *
     * @return array{0: string, 1: string}|null [mime, bytes]
     */
    private function downscale(string $path, string $mime): ?array
    {
        $size = @getimagesize($path);

        if (! $size || ! function_exists('imagecreatefromstring') || max($size[0], $size[1]) <= self::MAX_SIDE) {
            return null;
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));

        if (! $source) {
            return null;
        }

        $scale = self::MAX_SIDE / max($size[0], $size[1]);
        $resized = imagescale($source, max(1, (int) round($size[0] * $scale)), max(1, (int) round($size[1] * $scale)));
        imagedestroy($source);

        if (! $resized) {
            return null;
        }

        $keepAlpha = in_array($mime, ['image/png', 'image/webp', 'image/gif'], true);
        ob_start();

        if ($keepAlpha) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagepng($resized, null, 6);
        } else {
            imagejpeg($resized, null, 85);
        }

        imagedestroy($resized);

        return [$keepAlpha ? 'image/png' : 'image/jpeg', (string) ob_get_clean()];
    }
}
