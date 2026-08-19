<?php

namespace Database\Factories\Domains\Patients;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_uuid' => Str::uuid(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['male', 'female']),
            'birth_date' => fake()->date(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'city' => fake()->city(),
            'country_id' => null,
            'intake_center_id' => Center::factory(),
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
