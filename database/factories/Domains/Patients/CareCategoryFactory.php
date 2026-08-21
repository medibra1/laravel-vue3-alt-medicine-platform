<?php

namespace Database\Factories\Domains\Patients;

use App\Domains\Patients\Models\CareCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareCategory>
 */
class CareCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->word(),
            'label' => ['fr' => fake()->word(), 'en' => fake()->word()],
            'order' => 0,
            'active' => true,
        ];
    }
}
