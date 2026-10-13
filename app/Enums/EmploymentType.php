<?php

declare(strict_types=1);

namespace App\Enums;

/** Condicion laboral del participante (bases, VI). */
enum EmploymentType: string
{
    case Appointed = 'appointed';
    case Contracted = 'contracted';

    public function label(): string
    {
        return match ($this) {
            self::Appointed => 'Nombrado',
            self::Contracted => 'Contratado',
        };
    }
}
