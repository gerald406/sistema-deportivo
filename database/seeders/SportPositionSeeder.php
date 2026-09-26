<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Sport;
use App\Models\SportPosition;
use Illuminate\Database\Seeder;

class SportPositionSeeder extends Seeder
{
    public function run(): void
    {
        $futbol = Sport::where('slug', 'futbol')->firstOrFail();

        foreach (['Portero', 'Defensa', 'Mediocampista', 'Delantero'] as $i => $name) {
            SportPosition::updateOrCreate(
                ['sport_id' => $futbol->id, 'name' => $name],
                ['sort_order' => $i + 1]
            );
        }

        $voley = Sport::where('slug', 'voley')->firstOrFail();

        foreach (['Libero', 'Central', 'Opuesta', 'Pasadora', 'Punta', 'Universal'] as $i => $name) {
            SportPosition::updateOrCreate(
                ['sport_id' => $voley->id, 'name' => $name],
                ['sort_order' => $i + 1]
            );
        }
    }
}
