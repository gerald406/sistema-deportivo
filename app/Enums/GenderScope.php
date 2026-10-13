<?php

declare(strict_types=1);

namespace App\Enums;

/** A quien alcanza una fila del cuadro de puntaje. */
enum GenderScope: string
{
    case Male = 'male';
    case Female = 'female';
    case Both = 'both';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Varones',
            self::Female => 'Damas',
            self::Both => 'Damas y varones',
            self::None => 'Sin género',
        };
    }
}
