<?php

declare(strict_types=1);

namespace App\Enums;

/** Tipo de personal de la institucion (bases, VI). */
enum StaffType: string
{
    case Teacher = 'teacher';
    case Administrative = 'administrative';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'Docente',
            self::Administrative => 'Administrativo',
        };
    }
}
