<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\CareCategory;

test('guests are redirected to login', function () {
    $this->get(route('admin.care-categories.index'))->assertRedirect(route('login'));
});

test('manager cannot access the care categories list', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->get(route('admin.care-categories.index'));

    $response->assertForbidden();
});

test('super admin can list care categories', function () {
    $superAdmin = actingAsSuperAdmin();
    CareCategory::factory()->count(2)->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.care-categories.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['careCategories']['data'])->toHaveCount(2);
});

test('super admin can create a care category', function () {
    $superAdmin = actingAsSuperAdmin();

    $response = $this->actingAs($superAdmin)->post(route('admin.care-categories.store'), [
        'code' => 'ointment',
        'label' => ['fr' => 'Pommade', 'en' => 'Ointment'],
        'order' => 1,
        'active' => true,
    ]);

    $response->assertRedirect(route('admin.care-categories.index'));
    $careCategory = CareCategory::query()->where('code', 'ointment')->firstOrFail();
    expect($careCategory->getTranslation('label', 'fr'))->toBe('Pommade');
    expect($careCategory->getTranslation('label', 'en'))->toBe('Ointment');
    expect($careCategory->order)->toBe(1);
});

test('manager cannot create a care category', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.care-categories.store'), [
        'code' => 'bath',
        'label' => ['fr' => 'Bain', 'en' => 'Bath'],
    ]);

    $response->assertForbidden();
    expect(CareCategory::query()->where('code', 'bath')->count())->toBe(0);
});

test('creating a duplicate code fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    CareCategory::factory()->create(['code' => 'incense']);

    $response = $this->actingAs($superAdmin)->post(route('admin.care-categories.store'), [
        'code' => 'incense',
        'label' => ['fr' => 'Encens', 'en' => 'Incense'],
    ]);

    $response->assertSessionHasErrors('code');
    expect(CareCategory::query()->where('code', 'incense')->count())->toBe(1);
});

test('super admin can update a care category', function () {
    $superAdmin = actingAsSuperAdmin();
    $careCategory = CareCategory::factory()->create([
        'code' => 'tea',
        'label' => ['fr' => 'Tisane', 'en' => 'Tea'],
    ]);

    $response = $this->actingAs($superAdmin)->put(route('admin.care-categories.update', $careCategory), [
        'code' => 'tea',
        'label' => ['fr' => 'Nouvelle tisane', 'en' => 'New tea'],
        'order' => 3,
        'active' => false,
    ]);

    $response->assertRedirect(route('admin.care-categories.index'));
    $careCategory->refresh();
    expect($careCategory->getTranslation('label', 'fr'))->toBe('Nouvelle tisane');
    expect($careCategory->order)->toBe(3);
    expect($careCategory->active)->toBeFalse();
});

test('updating a care category keeping the same code does not fail uniqueness validation', function () {
    $superAdmin = actingAsSuperAdmin();
    $careCategory = CareCategory::factory()->create(['code' => 'verse']);

    $response = $this->actingAs($superAdmin)->put(route('admin.care-categories.update', $careCategory), [
        'code' => 'verse',
        'label' => ['fr' => 'Verset', 'en' => 'Verse'],
    ]);

    $response->assertRedirect(route('admin.care-categories.index'));
    $response->assertSessionHasNoErrors();
});

test('super admin can delete a care category', function () {
    $superAdmin = actingAsSuperAdmin();
    $careCategory = CareCategory::factory()->create();

    $response = $this->actingAs($superAdmin)->delete(route('admin.care-categories.destroy', $careCategory));

    $response->assertRedirect(route('admin.care-categories.index'));
    expect(CareCategory::query()->whereKey($careCategory->id)->exists())->toBeFalse();
});

test('manager cannot delete a care category', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $careCategory = CareCategory::factory()->create();

    $response = $this->actingAs($manager)->delete(route('admin.care-categories.destroy', $careCategory));

    $response->assertForbidden();
    expect(CareCategory::query()->whereKey($careCategory->id)->exists())->toBeTrue();
});
