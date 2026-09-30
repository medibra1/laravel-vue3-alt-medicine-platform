<?php

namespace Database\Factories\Domains\Scheduling;

use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\PractitionerAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PractitionerAvailability>
 */
class PractitionerAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'practitioner_id' => Practitioner::factory(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'start_time' => '09:00',
            'end_time' => '17:00',
        ];
    }
}
