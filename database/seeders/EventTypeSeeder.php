<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EventType;
use App\Models\Sport;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        $futbol = Sport::where('slug', 'futbol')->firstOrFail();

        $futbolEvents = [
            'goal' => 'Gol',
            'yellow_card' => 'Tarjeta amarilla',
            'red_card' => 'Tarjeta roja directa',
            'red_card_indirect' => 'Tarjeta roja por doble amarilla',
        ];

        foreach ($futbolEvents as $code => $name) {
            EventType::updateOrCreate(
                ['sport_id' => $futbol->id, 'code' => $code],
                ['name' => $name]
            );
        }

        $voley = Sport::where('slug', 'voley')->firstOrFail();

        $voleyEvents = [
            'block_point' => 'Punto de bloqueo',
            'service_ace' => 'Saque directo',
            'sanction' => 'Sancion disciplinaria',
        ];

        foreach ($voleyEvents as $code => $name) {
            EventType::updateOrCreate(
                ['sport_id' => $voley->id, 'code' => $code],
                ['name' => $name]
            );
        }
    }
}
