<?php

use App\Domains\Patients\Models\DiseaseCategory;
use Database\Seeders\DiseaseCategorySeeder;
use Database\Seeders\EnumOptionSeeder;

test('the symbols category is seeded with the SYMBOL type', function () {
    $this->seed(EnumOptionSeeder::class);
    $this->seed(DiseaseCategorySeeder::class);

    $category = DiseaseCategory::query()->where('code', '0')->with('type')->firstOrFail();

    expect($category->type->code)->toBe('SYMBOL');
    expect($category->order)->toBe(2);
    expect($category->diseases()->count())->toBeGreaterThan(0);
});

test('blockages are ordered before the illness categories', function () {
    $this->seed(EnumOptionSeeder::class);
    $this->seed(DiseaseCategorySeeder::class);

    $blockages = DiseaseCategory::query()->where('code', '8')->firstOrFail();

    expect($blockages->order)->toBe(1);
});
