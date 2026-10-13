<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StageMode;
use App\Models\Season;
use App\Models\SeasonStage;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeasonStageFactory extends Factory
{
    protected $model = SeasonStage::class;

    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'name' => 'Fase '.$this->faker->unique()->word(),
            'sort_order' => 1,
            'mode' => StageMode::Matches,
            'group_id' => null,
            'qualifiers' => null,
            'has_third_place' => false,
        ];
    }
}
