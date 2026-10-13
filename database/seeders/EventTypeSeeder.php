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
        // Futbol y futsal comparten codigos: las sanciones automaticas
        // (SuspensionService::AUTO_CODES) y los goleadores se resuelven por codigo.
        $cards = [
            'goal' => 'Gol',
            'yellow_card' => 'Tarjeta amarilla',
            'red_card' => 'Tarjeta roja directa',
            'red_card_indirect' => 'Tarjeta roja por doble amarilla',
        ];

        $bySport = [
            'futbol' => $cards,
            'futsal' => $cards,
            'basquetbol' => [
                'points' => 'Puntos',
                'foul' => 'Falta',
                'disqualification' => 'Descalificación',
            ],
            'voley' => [
                'block_point' => 'Punto de bloqueo',
                'service_ace' => 'Saque directo',
                'sanction' => 'Sancion disciplinaria',
            ],
        ];

        foreach ($bySport as $slug => $events) {
            $sport = Sport::where('slug', $slug)->firstOrFail();

            foreach ($events as $code => $name) {
                EventType::updateOrCreate(
                    ['sport_id' => $sport->id, 'code' => $code],
                    ['name' => $name]
                );
            }
        }
    }
}
