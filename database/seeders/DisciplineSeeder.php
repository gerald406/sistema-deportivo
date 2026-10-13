<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BetterDirection;
use App\Enums\SportFormatType;
use App\Enums\UnitOfMeasure;
use App\Models\Discipline;
use App\Models\Sport;
use Illuminate\Database\Seeder;

/** Pruebas de atletismo de las bases (Art. 1; especificacion §5.4). */
class DisciplineSeeder extends Seeder
{
    public function run(): void
    {
        $atletismo = Sport::where('slug', 'atletismo')->firstOrFail();

        // Nombre anterior de la prueba: se renombra en vez de duplicarla. La
        // comprobacion va en una consulta aparte: MySQL no acepta un UPDATE
        // con una subconsulta sobre la misma tabla (error 1093), aunque
        // MariaDB si.
        if (! Discipline::where('sport_id', $atletismo->id)->where('name', '100 metros')->exists()) {
            Discipline::where('sport_id', $atletismo->id)->where('name', '100 metros llanos')->update(['name' => '100 metros']);
        }

        $time = [UnitOfMeasure::Time, BetterDirection::Ascending];       // menor tiempo = mejor
        $distance = [UnitOfMeasure::Distance, BetterDirection::Descending]; // mayor distancia = mejor

        $events = [
            '100 metros' => [...$time, null],
            '400 metros' => [...$time, null],
            '1500 metros' => [...$time, null],
            '3000 metros' => [...$time, null],
            'Salto largo' => [...$distance, null],
            'Lanzamiento de peso' => [...$distance, null],
            'Lanzamiento de disco' => [...$distance, null],
            // Sobrescribe el formato del deporte padre: Atletismo es
            // individual_ranked, pero la posta se corre en equipo.
            'Posta 4x100' => [...$time, SportFormatType::TeamRelay],
        ];

        foreach ($events as $name => [$unit, $direction, $format]) {
            Discipline::updateOrCreate(
                ['sport_id' => $atletismo->id, 'name' => $name],
                [
                    'format_type' => $format,
                    'unit_of_measure' => $unit,
                    'better_direction' => $direction,
                    'is_active' => true,
                ]
            );
        }
    }
}
