<?php

namespace Database\Seeders;

use App\Domains\Common\Models\EnumOption;
use Illuminate\Database\Seeder;

class SessionMeasurementTypesSeeder extends Seeder
{
    /**
     * Session measurement types (blood pressure, glucose...) — open list,
     * extend/correct from the admin EnumOptions CRUD rather than editing
     * this seeder later. `properties.unit` is only a default pre-filled in
     * the session form, overridable per row; `properties.placeholder`
     * hints at the expected value format (e.g. "12/8" for blood pressure).
     */
    public function run(): void
    {
        $types = [
            ['code' => 'blood_pressure', 'label' => ['fr' => 'Tension artérielle', 'en' => 'Blood pressure'], 'order' => 1, 'properties' => ['unit' => 'mmHg', 'placeholder' => '12/8']],
            ['code' => 'blood_glucose', 'label' => ['fr' => 'Glycémie', 'en' => 'Blood glucose'], 'order' => 2, 'properties' => ['unit' => 'g/L']],
            ['code' => 'weight', 'label' => ['fr' => 'Poids', 'en' => 'Weight'], 'order' => 3, 'properties' => ['unit' => 'kg']],
            ['code' => 'temperature', 'label' => ['fr' => 'Température', 'en' => 'Temperature'], 'order' => 4, 'properties' => ['unit' => '°C']],
            ['code' => 'heart_rate', 'label' => ['fr' => 'Fréquence cardiaque', 'en' => 'Heart rate'], 'order' => 5, 'properties' => ['unit' => 'bpm']],
        ];

        foreach ($types as $type) {
            EnumOption::query()->firstOrCreate(
                ['enum_type' => 'session_measurement_type', 'code' => $type['code']],
                [
                    'label' => $type['label'],
                    'order' => $type['order'],
                    'active' => true,
                    'properties' => $type['properties'],
                ]
            );
        }
    }
}
