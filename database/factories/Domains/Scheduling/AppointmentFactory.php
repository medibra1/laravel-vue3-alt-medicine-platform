<?php

namespace Database\Factories\Domains\Scheduling;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'practitioner_id' => Practitioner::factory(),
            'patient_id' => Patient::factory(),
            'treatment_id' => null,
            'treatment_session_id' => null,
            'starts_at' => now()->addDays(fake()->numberBetween(2, 20))->setTime(fake()->numberBetween(9, 16), 0),
            'duration_minutes' => fake()->randomElement([30, 45, 60]),
            'status' => 'scheduled',
            'modality' => 'in_person',
            'meeting_link' => null,
            'reason' => null,
            'cancellation_reason' => null,
            'created_by' => User::factory(),
        ];
    }

    public function remote(): static
    {
        return $this->state(fn () => [
            'modality' => 'remote',
            'meeting_link' => 'https://meet.example.com/'.fake()->lexify('???-????-???'),
        ]);
    }
}
