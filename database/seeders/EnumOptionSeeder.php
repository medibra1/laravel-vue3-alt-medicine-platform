<?php

namespace Database\Seeders;

use App\Domains\Common\Models\EnumOption;
use Illuminate\Database\Seeder;

class EnumOptionSeeder extends Seeder
{
    /**
     * Reference dynamic options. Always idempotent (firstOrCreate by
     * enum_type + code) — this seeder is safe to re-run without
     * duplicating anything.
     */
    public function run(): void
    {
        // Disease category types: illness (categories 1-7, generally
        // treated by doctors), blockage, nightmare (new).
        $categoryTypes = [
            ['code' => 'ILLNESS', 'label' => ['fr' => 'Maladie', 'en' => 'Illness'], 'order' => 1],
            ['code' => 'BLOCKAGE', 'label' => ['fr' => 'Blocage', 'en' => 'Blockage'], 'order' => 2],
            ['code' => 'NIGHTMARE', 'label' => ['fr' => 'Cauchemars', 'en' => 'Nightmares'], 'order' => 3],
        ];

        foreach ($categoryTypes as $type) {
            EnumOption::query()->firstOrCreate(
                ['enum_type' => 'disease_category.type', 'code' => $type['code']],
                [
                    'label' => $type['label'],
                    'order' => $type['order'],
                    'active' => true,
                ]
            );
        }

        // Payroll organism types — used only by centers running in
        // "conventional" payroll mode (see PayrollMode enum). Generic
        // enough to fit most countries' social security / tax bodies;
        // extend per-country needs by adding options here, not by
        // changing the schema.
        $organismTypes = [
            ['code' => 'TAX', 'label' => ['fr' => 'Impôts', 'en' => 'Tax authority'], 'order' => 1],
            ['code' => 'SOCIAL_SECURITY', 'label' => ['fr' => 'Sécurité sociale', 'en' => 'Social security'], 'order' => 2],
            ['code' => 'PENSION', 'label' => ['fr' => 'Retraite', 'en' => 'Pension'], 'order' => 3],
            ['code' => 'HEALTH_INSURANCE', 'label' => ['fr' => 'Assurance maladie', 'en' => 'Health insurance'], 'order' => 4],
            ['code' => 'OTHER', 'label' => ['fr' => 'Autre', 'en' => 'Other'], 'order' => 5],
        ];

        foreach ($organismTypes as $type) {
            EnumOption::query()->firstOrCreate(
                ['enum_type' => 'payroll_organism.type', 'code' => $type['code']],
                [
                    'label' => $type['label'],
                    'order' => $type['order'],
                    'active' => true,
                ]
            );
        }
    }
}
