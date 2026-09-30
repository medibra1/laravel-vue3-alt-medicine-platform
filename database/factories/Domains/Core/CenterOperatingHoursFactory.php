<?php

namespace Database\Factories\Domains\Core;

use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\CenterOperatingHours;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CenterOperatingHours>
 */
class CenterOperatingHoursFactory extends Factory
{
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'day_of_week' => fake()->numberBetween(1, 6),
            'start_time' => '08:00',
            'end_time' => '18:00',
        ];
    }
}
