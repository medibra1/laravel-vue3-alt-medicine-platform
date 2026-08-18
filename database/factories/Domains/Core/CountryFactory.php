<?php

namespace Database\Factories\Domains\Core;

use App\Domains\Core\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'zone_id' => null,
            'code' => fake()->unique()->numerify('##'),
            'name' => ['fr' => fake()->country(), 'en' => fake()->country()],
            'active' => true,
        ];
    }
}
