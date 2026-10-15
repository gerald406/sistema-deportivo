<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Fases que "Crear fases estandar" arma para una competencia segun su
 * deporte (sports.standard_stages; vacio = se deduce del formato).
 */
enum StandardStages: string
{
    /** Una fase de partidos por serie (clasifica 1) + "Fase final" con tercer puesto (Art. 8 y 10). */
    case BracketSeries = 'bracket_series';

    /** Una fase de podio directo por serie (clasifica 1) + "Final" de podio directo (ajedrez, sistema suizo fuera del sistema). */
    case PodiumSeries = 'podium_series';

    /** Solo una "Final" de podio directo. */
    case PodiumOnly = 'podium_only';

    public function label(): string
    {
        return match ($this) {
            self::BracketSeries => 'Series en llave + fase final',
            self::PodiumSeries => 'Series de podio + final',
            self::PodiumOnly => 'Solo podio',
        };
    }

    /** Lo que se usa cuando el deporte no lo define: enfrentamiento = series en llave; lo demas = solo podio. */
    public static function forFormat(SportFormatType $format): self
    {
        return $format === SportFormatType::HeadToHead ? self::BracketSeries : self::PodiumOnly;
    }
}
