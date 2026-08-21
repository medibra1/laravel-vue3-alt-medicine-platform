<?php

namespace Database\Factories\Domains\Patients;

use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\DiseaseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disease>
 */
class DiseaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'disease_category_id' => DiseaseCategory::factory(),
            'code' => fake()->unique()->numerify('###'),
            'label' => ['fr' => fake()->word(), 'en' => fake()->word()],
            'description' => null,
            'default_duration_months' => fake()->numberBetween(1, 12),
            'active' => true,
        ];
    }
}
