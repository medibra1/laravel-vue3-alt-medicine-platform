<?php

namespace Database\Factories\Domains\Patients;

use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\CareItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareItem>
 */
class CareItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'care_category_id' => CareCategory::factory(),
            'code' => fake()->unique()->numerify('###'),
            'label' => ['fr' => fake()->word(), 'en' => fake()->word()],
            'description' => null,
            'order' => 0,
            'active' => true,
        ];
    }
}
