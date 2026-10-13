<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GenderScope;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class GamesEditionEventFactory extends Factory
{
    protected $model = GamesEditionEvent::class;

    public function definition(): array
    {
        return [
            'games_edition_id' => GamesEdition::factory(),
            'sort_order' => 1,
            'label' => 'Fila '.$this->faker->unique()->word(),
            'sport_id' => null,
            'category_id' => null,
            'gender_scope' => GenderScope::None,
            'separate_podiums' => true,
            'per_discipline' => false,
            'pts_first' => null,
            'pts_second' => null,
            'pts_third' => null,
            'min_players' => null,
            'max_players' => null,
            'rules_blocking' => false,
            'is_active' => true,
        ];
    }
}
