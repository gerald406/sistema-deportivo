<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Gender;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Libre', 'min_age' => null, 'max_age' => null, 'gender' => Gender::Mixed],
            ['name' => 'Sub-17', 'min_age' => 15, 'max_age' => 17, 'gender' => Gender::Mixed],
            ['name' => 'Damas', 'min_age' => null, 'max_age' => null, 'gender' => Gender::Female],
            ['name' => 'Varones', 'min_age' => null, 'max_age' => null, 'gender' => Gender::Male],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                $category + ['is_active' => true]
            );
        }
    }
}
