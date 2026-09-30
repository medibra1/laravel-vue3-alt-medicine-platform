<?php

namespace Database\Factories\Domains\Scheduling;

use App\Domains\Auth\Models\User;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\PractitionerTimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PractitionerTimeOff>
 */
class PractitionerTimeOffFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(5, 30))->startOfDay();

        return [
            'practitioner_id' => Practitioner::factory(),
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addDays(fake()->numberBetween(0, 4))->toDateString(),
            'reason' => fake()->randomElement(['vacation', 'sick_leave', 'training', 'other']),
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
