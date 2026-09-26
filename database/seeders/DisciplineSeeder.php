<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BetterDirection;
use App\Enums\SportFormatType;
use App\Enums\UnitOfMeasure;
use App\Models\Discipline;
use App\Models\Sport;
use Illuminate\Database\Seeder;

class DisciplineSeeder extends Seeder
{
    public function run(): void
    {
        $atletismo = Sport::where('slug', 'atletismo')->firstOrFail();

        Discipline::updateOrCreate(
            ['sport_id' => $atletismo->id, 'name' => '100 metros llanos'],
            [
                'unit_of_measure' => UnitOfMeasure::Time,
                'better_direction' => BetterDirection::Ascending, // menor tiempo = mejor
                'is_active' => true,
            ]
        );

        Discipline::updateOrCreate(
            ['sport_id' => $atletismo->id, 'name' => 'Salto largo'],
            [
                'unit_of_measure' => UnitOfMeasure::Distance,
                'better_direction' => BetterDirection::Descending, // mayor distancia = mejor
                'is_active' => true,
            ]
        );

        Discipline::updateOrCreate(
            ['sport_id' => $atletismo->id, 'name' => 'Posta 4x100'],
            [
                // Sobrescribe el formato del deporte padre: Atletismo es
                // individual_ranked, pero la posta se corre en equipo.
                'format_type' => SportFormatType::TeamRelay,
                'unit_of_measure' => UnitOfMeasure::Time,
                'better_direction' => BetterDirection::Ascending,
                'is_active' => true,
            ]
        );

        $ajedrez = Sport::where('slug', 'ajedrez')->firstOrFail();

        Discipline::updateOrCreate(
            ['sport_id' => $ajedrez->id, 'name' => 'Ajedrez clasico'],
            [
                'unit_of_measure' => UnitOfMeasure::Points,
                'better_direction' => BetterDirection::Descending,
                'is_active' => true,
            ]
        );
    }
}
