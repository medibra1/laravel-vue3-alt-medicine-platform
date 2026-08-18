<?php

namespace Database\Factories\Domains\Core;

use App\Domains\Core\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('grade-????'),
            'label' => ['fr' => 'Confirmé', 'en' => 'Confirmed'],
            'coefficient' => 1.00,
            'order' => 0,
            'active' => true,
        ];
    }
}
