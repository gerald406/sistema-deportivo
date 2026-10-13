<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EditionAward;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionAwardFactory extends Factory
{
    protected $model = EditionAward::class;

    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'games_edition_id' => fn (array $attributes) => Season::find($attributes['season_id'])->games_edition_id,
            'games_edition_event_id' => null,
            'team_id' => Team::factory(),
            'place' => 1,
            'points' => 0,
        ];
    }
}
