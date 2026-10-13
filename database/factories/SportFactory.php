<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SportFormatType;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SportFactory extends Factory
{
    protected $model = Sport::class;

    public function definition(): array
    {
        $name = 'Deporte '.$this->faker->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'format_type' => SportFormatType::HeadToHead,
            'config' => null,
            'is_active' => true,
        ];
    }
}
