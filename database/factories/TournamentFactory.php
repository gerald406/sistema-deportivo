<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    public function definition(): array
    {
        return [
            'name' => 'Liga '.$this->faker->city(),
            'logo' => null,
            'organizer_id' => User::factory(),
            'is_active' => true,
        ];
    }
}
