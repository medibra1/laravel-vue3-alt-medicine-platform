<?php

namespace Database\Factories\Domains\Patients;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Treatment>
 */
class TreatmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_uuid' => Str::uuid(),
            'patient_id' => Patient::factory(),
            'practitioner_id' => Practitioner::factory(),
            'center_id' => Center::factory(),
            'started_at' => fake()->date(),
            'ended_at' => null,
            'outcome' => null,
            'outcome_percentage' => null,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
