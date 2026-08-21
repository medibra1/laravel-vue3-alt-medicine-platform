<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\DiseaseCategory;

test('guests are redirected to login', function () {
    $this->get(route('admin.diseases.index'))->assertRedirect(route('login'));
});

test('manager cannot access the diseases list', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->get(route('admin.diseases.index'));

    $response->assertForbidden();
});

test('super admin can list diseases', function () {
    $superAdmin = actingAsSuperAdmin();
    Disease::factory()->count(2)->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.diseases.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['diseases']['data'])->toHaveCount(2);
});

test('super admin can create a disease with an explicit code', function () {
    $superAdmin = actingAsSuperAdmin();
    $category = DiseaseCategory::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.diseases.store'), [
        'disease_category_id' => $category->id,
        'code' => '005',
        'label' => ['fr' => 'Acidité', 'en' => 'Acidity'],
        'description' => ['fr' => 'Une description', 'en' => 'A description'],
        'default_duration_months' => 3,
    ]);

    $response->assertRedirect(route('admin.diseases.index'));
    $disease = Disease::query()->where('disease_category_id', $category->id)->firstOrFail();
    expect($disease->code)->toBe('005');
    expect($disease->getTranslation('label', 'fr'))->toBe('Acidité');
    expect($disease->getTranslation('description', 'en'))->toBe('A description');
    expect($disease->default_duration_months)->toBe(3);
});

test('manager cannot create a disease', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $category = DiseaseCategory::factory()->create();

    $response = $this->actingAs($manager)->post(route('admin.diseases.store'), [
        'disease_category_id' => $category->id,
        'code' => '005',
        'label' => ['fr' => 'Acidité', 'en' => 'Acidity'],
        'default_duration_months' => 3,
    ]);

    $response->assertForbidden();
    expect(Disease::query()->where('disease_category_id', $category->id)->count())->toBe(0);
});

test('creating a duplicate code within the same category fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    $category = DiseaseCategory::factory()->create();
    Disease::factory()->for($category, 'category')->create(['code' => '005']);

    $response = $this->actingAs($superAdmin)->post(route('admin.diseases.store'), [
        'disease_category_id' => $category->id,
        'code' => '005',
        'label' => ['fr' => 'Doublon', 'en' => 'Duplicate'],
        'default_duration_months' => 1,
    ]);

    $response->assertSessionHasErrors('code');
    expect(Disease::query()->where('disease_category_id', $category->id)->count())->toBe(1);
});

test('the same code is allowed in a different category', function () {
    $superAdmin = actingAsSuperAdmin();
    $categoryA = DiseaseCategory::factory()->create();
    $categoryB = DiseaseCategory::factory()->create();
    Disease::factory()->for($categoryA, 'category')->create(['code' => '005']);

    $response = $this->actingAs($superAdmin)->post(route('admin.diseases.store'), [
        'disease_category_id' => $categoryB->id,
        'code' => '005',
        'label' => ['fr' => 'Maladie B', 'en' => 'Disease B'],
        'default_duration_months' => 1,
    ]);

    $response->assertRedirect(route('admin.diseases.index'));
    expect(Disease::query()->where('disease_category_id', $categoryB->id)->count())->toBe(1);
});

test('next-code endpoint suggests the next free code for a category', function () {
    $superAdmin = actingAsSuperAdmin();
    $category = DiseaseCategory::factory()->create();
    Disease::factory()->for($category, 'category')->create(['code' => '001']);
    Disease::factory()->for($category, 'category')->create(['code' => '002']);

    $response = $this->actingAs($superAdmin)->getJson(
        route('admin.diseases.next-code', ['category_id' => $category->id]),
    );

    $response->assertOk();
    expect($response->json('code'))->toBe('003');
});

test('super admin can update a disease', function () {
    $superAdmin = actingAsSuperAdmin();
    $disease = Disease::factory()->create(['label' => ['fr' => 'Ancien', 'en' => 'Old']]);

    $response = $this->actingAs($superAdmin)->put(route('admin.diseases.update', $disease), [
        'disease_category_id' => $disease->disease_category_id,
        'code' => $disease->code,
        'label' => ['fr' => 'Nouveau', 'en' => 'New'],
        'description' => ['fr' => null, 'en' => null],
        'default_duration_months' => $disease->default_duration_months,
    ]);

    $response->assertRedirect(route('admin.diseases.index'));
    expect($disease->fresh()->getTranslation('label', 'fr'))->toBe('Nouveau');
});

test('super admin can delete a disease', function () {
    $superAdmin = actingAsSuperAdmin();
    $disease = Disease::factory()->create();

    $response = $this->actingAs($superAdmin)->delete(route('admin.diseases.destroy', $disease));

    $response->assertRedirect(route('admin.diseases.index'));
    expect(Disease::query()->whereKey($disease->id)->exists())->toBeFalse();
});

test('manager cannot delete a disease', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $disease = Disease::factory()->create();

    $response = $this->actingAs($manager)->delete(route('admin.diseases.destroy', $disease));

    $response->assertForbidden();
    expect(Disease::query()->whereKey($disease->id)->exists())->toBeTrue();
});
