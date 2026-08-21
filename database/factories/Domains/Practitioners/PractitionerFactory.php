<?php

namespace Database\Factories\Domains\Practitioners;

use App\Domains\Core\Models\Center;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Practitioner>
 */
class PractitionerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'user_id' => null,
            'center_id' => Center::factory(),
            'grade_id' => null,
            'matricule' => fake()->unique()->numerify('###'),
            'level' => null,
            'hired_at' => null,
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'email' => fake()->safeEmail(),
        ];
    }
}
