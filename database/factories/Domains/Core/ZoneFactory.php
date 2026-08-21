<?php

namespace Database\Factories\Domains\Core;

use App\Domains\Core\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('ZONE_????'),
            'name' => ['fr' => fake()->word(), 'en' => fake()->word()],
            'order' => 0,
            'active' => true,
        ];
    }
}
