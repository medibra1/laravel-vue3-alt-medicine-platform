<?php

namespace Database\Factories\Domains\Core;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\CenterClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CenterClosure>
 */
class CenterClosureFactory extends Factory
{
    public function definition(): array
    {
        $day = now()->addDays(fake()->numberBetween(5, 30))->toDateString();

        return [
            'center_id' => Center::factory(),
            'starts_on' => $day,
            'ends_on' => $day,
            'label' => 'Jour férié',
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
