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
        // Disease category types: illness (categories 3-9, generally
        // treated by doctors), blockage, symbol. The "nightmare" type
        // (NIGHTMARE) was removed 2026-08-24 along with its category —
        // never had real source content, replaced by "Symboles".
        $categoryTypes = [
            ['code' => 'ILLNESS', 'label' => ['fr' => 'Maladie', 'en' => 'Illness'], 'order' => 1],
            ['code' => 'BLOCKAGE', 'label' => ['fr' => 'Blocage', 'en' => 'Blockage'], 'order' => 2],
            ['code' => 'SYMBOL', 'label' => ['fr' => 'Symboles', 'en' => 'Symbols'], 'order' => 3],
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

        // Patient religion — open list, extend/correct from the admin
        // EnumOptions CRUD rather than editing this seeder later.
        $religions = [
            ['code' => 'MUSLIM', 'label' => ['fr' => 'Musulmane', 'en' => 'Muslim'], 'order' => 1],
            ['code' => 'CHRISTIAN', 'label' => ['fr' => 'Chrétienne', 'en' => 'Christian'], 'order' => 2],
            ['code' => 'JEWISH', 'label' => ['fr' => 'Juive', 'en' => 'Jewish'], 'order' => 3],
            ['code' => 'OTHER', 'label' => ['fr' => 'Autre', 'en' => 'Other'], 'order' => 4],
            ['code' => 'NONE', 'label' => ['fr' => 'Aucune / non renseignée', 'en' => 'None / undisclosed'], 'order' => 5],
        ];

        foreach ($religions as $religion) {
            EnumOption::query()->firstOrCreate(
                ['enum_type' => 'patient.religion', 'code' => $religion['code']],
                [
                    'label' => $religion['label'],
                    'order' => $religion['order'],
                    'active' => true,
                ]
            );
        }
    }
}
