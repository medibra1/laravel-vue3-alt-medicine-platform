<?php

namespace Database\Factories\Domains\Core;

use App\Domains\Core\Enums\PayrollMode;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Center>
 */
class CenterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'code' => fake()->unique()->numerify('##'),
            'name' => fake()->city().' Center',
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'active' => true,
            'payroll_mode' => PayrollMode::PoolSharing,
        ];
    }
}
