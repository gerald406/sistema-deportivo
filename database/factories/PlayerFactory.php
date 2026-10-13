<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            // 8 digitos: cumple el CHECK chk_players_dni_length (8-12).
            'dni' => $this->faker->unique()->numerify('########'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            // Mayores de 25 al 2026: entran al nivel Libre de las bases.
            'birth_date' => $this->faker->dateTimeBetween('1965-01-01', '2000-12-31'),
            'gender' => $this->faker->randomElement(['M', 'F']),
            'staff_type' => 'teacher',
            'employment_type' => 'appointed',
            'photo_path' => null,
            'is_active' => true,
        ];
    }
}
