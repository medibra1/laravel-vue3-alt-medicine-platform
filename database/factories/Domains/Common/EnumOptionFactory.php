<?php

namespace Database\Factories\Domains\Common;

use App\Domains\Common\Models\EnumOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnumOption>
 */
class EnumOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'enum_type' => 'disease_category.type',
            'domain' => null,
            'code' => fake()->unique()->word(),
            'label' => ['fr' => fake()->word(), 'en' => fake()->word()],
            'parent_id' => null,
            'order' => 0,
            'active' => true,
            'properties' => null,
        ];
    }
}
