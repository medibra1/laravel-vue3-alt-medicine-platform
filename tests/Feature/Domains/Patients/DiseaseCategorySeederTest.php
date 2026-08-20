<?php

use App\Domains\Patients\Models\DiseaseCategory;
use Database\Seeders\DiseaseCategorySeeder;
use Database\Seeders\EnumOptionSeeder;

test('the nightmares category is seeded with the NIGHTMARE type', function () {
    $this->seed(EnumOptionSeeder::class);
    $this->seed(DiseaseCategorySeeder::class);

    $category = DiseaseCategory::query()->where('code', '9')->with('type')->firstOrFail();

    expect($category->type->code)->toBe('NIGHTMARE');
    expect($category->diseases()->count())->toBeGreaterThan(0);
});
