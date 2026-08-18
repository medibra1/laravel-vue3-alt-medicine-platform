<?php

namespace Database\Seeders;

use App\Domains\Core\Models\Grade;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    /**
     * Reference grades — placeholder values. The real grid (grade names
     * and coefficients) will be defined with the payroll module.
     */
    public function run(): void
    {
        $grades = [
            ['code' => 'JUNIOR', 'label' => ['fr' => 'Junior', 'en' => 'Junior'], 'coefficient' => 1.00, 'order' => 1],
            ['code' => 'CONFIRME', 'label' => ['fr' => 'Confirmé', 'en' => 'Confirmed'], 'coefficient' => 1.15, 'order' => 2],
            ['code' => 'SENIOR', 'label' => ['fr' => 'Senior', 'en' => 'Senior'], 'coefficient' => 1.30, 'order' => 3],
        ];

        foreach ($grades as $grade) {
            Grade::query()->firstOrCreate(['code' => $grade['code']], $grade);
        }
    }
}
