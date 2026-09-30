<?php

namespace Database\Factories\Domains\Patients;

use App\Domains\Patients\Models\ConsentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentTemplate>
 */
class ConsentTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'treatment',
            'version' => 1,
            'title' => 'Consentement au traitement',
            'content' => fake()->paragraphs(3, true),
            'is_active' => true,
        ];
    }
}
