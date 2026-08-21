<?php

namespace Database\Factories\Domains\Patients;

use App\Domains\Common\Models\EnumOption;
use App\Domains\Patients\Models\DiseaseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiseaseCategory>
 */
class DiseaseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type_option_id' => EnumOption::factory(),
            'code' => fake()->unique()->numerify('#'),
            'label' => ['fr' => fake()->word(), 'en' => fake()->word()],
            'order' => 0,
            'active' => true,
        ];
    }
}
