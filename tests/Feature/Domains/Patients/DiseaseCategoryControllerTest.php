<?php

use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\DiseaseCategory;

test('guests are redirected to login', function () {
    $this->get(route('admin.disease-categories.index'))->assertRedirect(route('login'));
});

test('manager cannot access the disease categories list', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->get(route('admin.disease-categories.index'));

    $response->assertForbidden();
});

test('super admin can list disease categories', function () {
    $superAdmin = actingAsSuperAdmin();
    DiseaseCategory::factory()->count(2)->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.disease-categories.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['categories']['data'])->toHaveCount(2);
});

test('super admin can create a disease category', function () {
    $superAdmin = actingAsSuperAdmin();
    $type = EnumOption::factory()->create(['enum_type' => 'disease_category.type']);

    $response = $this->actingAs($superAdmin)->post(route('admin.disease-categories.store'), [
        'type_option_id' => $type->id,
        'code' => '9',
        'label' => ['fr' => 'Cauchemars', 'en' => 'Nightmares'],
    ]);

    $response->assertRedirect(route('admin.disease-categories.index'));
    $category = DiseaseCategory::query()->where('code', '9')->firstOrFail();
    expect($category->getTranslation('label', 'fr'))->toBe('Cauchemars');
    expect($category->getTranslation('label', 'en'))->toBe('Nightmares');
});

test('manager cannot create a disease category', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $type = EnumOption::factory()->create(['enum_type' => 'disease_category.type']);

    $response = $this->actingAs($manager)->post(route('admin.disease-categories.store'), [
        'type_option_id' => $type->id,
        'code' => '9',
        'label' => ['fr' => 'Cauchemars', 'en' => 'Nightmares'],
    ]);

    $response->assertForbidden();
    expect(DiseaseCategory::query()->where('code', '9')->count())->toBe(0);
});

test('creating a duplicate code fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    $type = EnumOption::factory()->create(['enum_type' => 'disease_category.type']);
    DiseaseCategory::factory()->create(['code' => '9']);

    $response = $this->actingAs($superAdmin)->post(route('admin.disease-categories.store'), [
        'type_option_id' => $type->id,
        'code' => '9',
        'label' => ['fr' => 'Doublon', 'en' => 'Duplicate'],
    ]);

    $response->assertSessionHasErrors('code');
    expect(DiseaseCategory::query()->where('code', '9')->count())->toBe(1);
});

test('super admin can update a disease category', function () {
    $superAdmin = actingAsSuperAdmin();
    $category = DiseaseCategory::factory()->create();
    $newType = EnumOption::factory()->create(['enum_type' => 'disease_category.type']);

    $response = $this->actingAs($superAdmin)->put(route('admin.disease-categories.update', $category), [
        'type_option_id' => $newType->id,
        'code' => $category->code,
        'label' => ['fr' => 'Nouveau nom', 'en' => 'New name'],
        'order' => 5,
        'active' => false,
    ]);

    $response->assertRedirect(route('admin.disease-categories.index'));
    $category->refresh();
    expect($category->getTranslation('label', 'fr'))->toBe('Nouveau nom');
    expect($category->order)->toBe(5);
    expect($category->active)->toBeFalse();
});

test('super admin can delete a disease category', function () {
    $superAdmin = actingAsSuperAdmin();
    $category = DiseaseCategory::factory()->create();

    $response = $this->actingAs($superAdmin)->delete(route('admin.disease-categories.destroy', $category));

    $response->assertRedirect(route('admin.disease-categories.index'));
    expect(DiseaseCategory::query()->whereKey($category->id)->exists())->toBeFalse();
});

test('manager cannot delete a disease category', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $category = DiseaseCategory::factory()->create();

    $response = $this->actingAs($manager)->delete(route('admin.disease-categories.destroy', $category));

    $response->assertForbidden();
    expect(DiseaseCategory::query()->whereKey($category->id)->exists())->toBeTrue();
});
