<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        return [
            'name' => 'Estadio '.$this->faker->city(),
            'address' => $this->faker->address(),
            'latitude' => $this->faker->latitude(-18, 0),
            'longitude' => $this->faker->longitude(-81, -68),
            'is_active' => true,
        ];
    }
}
