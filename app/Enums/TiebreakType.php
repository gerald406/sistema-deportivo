<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Via por la que se desempato un partido de llave. El sistema registra
 * quien gano y como; no simula el desempate.
 */
enum TiebreakType: string
{
    case ExtraTime = 'extra_time';
    case Penalties = 'penalties';
    case FreeThrows = 'free_throws';
    case CoinToss = 'coin_toss';

    public function label(): string
    {
        return match ($this) {
            self::ExtraTime => 'Tiempo extra',
            self::Penalties => 'Penales',
            self::FreeThrows => 'Tiros libres',
            self::CoinToss => 'Sorteo (moneda)',
        };
    }
}
