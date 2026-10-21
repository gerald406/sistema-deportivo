<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Venue;

/**
 * Enlaces "Como llegar" de una sede en la pagina publica: Google Maps en
 * modo ruta (desde donde esta la persona; en el celular abre la app) y
 * OpenStreetMap como alternativa libre. Con coordenadas van al punto
 * exacto; sin ellas se busca por nombre y direccion.
 */
class VenueMap
{
    public static function hasCoordinates(Venue $venue): bool
    {
        return $venue->latitude !== null && $venue->longitude !== null;
    }

    public static function directions(Venue $venue): string
    {
        return 'https://www.google.com/maps/dir/?'.http_build_query([
            'api' => 1,
            'destination' => self::hasCoordinates($venue) ? self::point($venue) : self::text($venue),
        ]);
    }

    public static function openStreetMap(Venue $venue): string
    {
        return self::hasCoordinates($venue)
            // route = "origen;destino": sin origen, la persona elige desde donde sale.
            ? 'https://www.openstreetmap.org/directions?'.http_build_query(['route' => ';'.self::point($venue)])
            : 'https://www.openstreetmap.org/search?'.http_build_query(['query' => self::text($venue)]);
    }

    private static function point(Venue $venue): string
    {
        return (float) $venue->latitude.','.(float) $venue->longitude;
    }

    /** Nombre y direccion; si la direccion no dice la ciudad, la busqueda puede caer en otra con el mismo nombre. */
    private static function text(Venue $venue): string
    {
        return trim($venue->name.($venue->address ? ', '.$venue->address : ''));
    }
}
