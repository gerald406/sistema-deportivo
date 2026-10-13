<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GamesEdition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GamesEditionFactory extends Factory
{
    protected $model = GamesEdition::class;

    public function definition(): array
    {
        return [
            'name' => 'Olimpiada '.$this->faker->unique()->city(),
            'organizer_id' => User::factory(),
            'start_date' => '2026-10-28',
            'end_date' => '2026-10-30',
            'is_active' => true,
        ];
    }
}
