<?php

namespace Database\Factories\Domains\Patients;

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Patients\Models\TreatmentSession;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentSession>
 */
class TreatmentSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'treatment_id' => Treatment::factory(),
            'practitioner_id' => Practitioner::factory(),
            'session_date' => fake()->date(),
            'duration_minutes' => fake()->numberBetween(15, 90),
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
