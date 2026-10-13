<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SeasonStatus;
use App\Models\GamesEdition;
use App\Models\Season;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeasonFactory extends Factory
{
    protected $model = Season::class;

    public function definition(): array
    {
        return [
            'games_edition_id' => GamesEdition::factory(),
            'sport_id' => Sport::factory(),
            'name' => 'Competencia '.$this->faker->unique()->word(),
            'status' => SeasonStatus::Active,
            'is_active' => true,
        ];
    }
}
