<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\CareItem;

test('guests are redirected to login', function () {
    $this->get(route('admin.care-items.index'))->assertRedirect(route('login'));
});

test('manager cannot access the care items list', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->get(route('admin.care-items.index'));

    $response->assertForbidden();
});

test('super admin can list care items', function () {
    $superAdmin = actingAsSuperAdmin();
    CareItem::factory()->count(2)->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.care-items.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['careItems']['data'])->toHaveCount(2);
});

test('super admin can create a care item with an explicit code', function () {
    $superAdmin = actingAsSuperAdmin();
    $category = CareCategory::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.care-items.store'), [
        'care_category_id' => $category->id,
        'code' => '005',
        'label' => ['fr' => 'Pommade à la nigelle', 'en' => 'Black seed ointment'],
        'description' => ['fr' => 'Description FR', 'en' => 'Description EN'],
    ]);

    $response->assertRedirect(route('admin.care-items.index'));
    $careItem = CareItem::query()->where('care_category_id', $category->id)->firstOrFail();
    expect($careItem->code)->toBe('005');
    expect($careItem->getTranslation('label', 'fr'))->toBe('Pommade à la nigelle');
    expect($careItem->getTranslation('description', 'en'))->toBe('Description EN');
});

test('manager cannot create a care item', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $category = CareCategory::factory()->create();

    $response = $this->actingAs($manager)->post(route('admin.care-items.store'), [
        'care_category_id' => $category->id,
        'code' => '005',
        'label' => ['fr' => 'Test', 'en' => 'Test'],
    ]);

    $response->assertForbidden();
    expect(CareItem::query()->where('care_category_id', $category->id)->count())->toBe(0);
});

test('creating a duplicate code within the same category fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    $category = CareCategory::factory()->create();
    CareItem::factory()->for($category, 'category')->create(['code' => '005']);

    $response = $this->actingAs($superAdmin)->post(route('admin.care-items.store'), [
        'care_category_id' => $category->id,
        'code' => '005',
        'label' => ['fr' => 'Doublon', 'en' => 'Duplicate'],
    ]);

    $response->assertSessionHasErrors('code');
    expect(CareItem::query()->where('care_category_id', $category->id)->count())->toBe(1);
});

test('the same code is allowed in a different category', function () {
    $superAdmin = actingAsSuperAdmin();
    $categoryA = CareCategory::factory()->create();
    $categoryB = CareCategory::factory()->create();
    CareItem::factory()->for($categoryA, 'category')->create(['code' => '005']);

    $response = $this->actingAs($superAdmin)->post(route('admin.care-items.store'), [
        'care_category_id' => $categoryB->id,
        'code' => '005',
        'label' => ['fr' => 'Soin B', 'en' => 'Care B'],
    ]);

    $response->assertRedirect(route('admin.care-items.index'));
    expect(CareItem::query()->where('care_category_id', $categoryB->id)->count())->toBe(1);
});

test('next-code endpoint suggests the next free code for a category', function () {
    $superAdmin = actingAsSuperAdmin();
    $category = CareCategory::factory()->create();
    CareItem::factory()->for($category, 'category')->create(['code' => '001']);
    CareItem::factory()->for($category, 'category')->create(['code' => '002']);

    $response = $this->actingAs($superAdmin)->getJson(
        route('admin.care-items.next-code', ['category_id' => $category->id]),
    );

    $response->assertOk();
    expect($response->json('code'))->toBe('003');
});

test('super admin can update a care item', function () {
    $superAdmin = actingAsSuperAdmin();
    $careItem = CareItem::factory()->create();

    $response = $this->actingAs($superAdmin)->put(route('admin.care-items.update', $careItem), [
        'care_category_id' => $careItem->care_category_id,
        'code' => $careItem->code,
        'label' => ['fr' => 'Nouveau libellé', 'en' => 'New label'],
        'description' => ['fr' => 'Nouvelle description', 'en' => 'New description'],
    ]);

    $response->assertRedirect(route('admin.care-items.index'));
    $careItem->refresh();
    expect($careItem->getTranslation('label', 'fr'))->toBe('Nouveau libellé');
    expect($careItem->getTranslation('description', 'fr'))->toBe('Nouvelle description');
});

test('super admin can delete a care item', function () {
    $superAdmin = actingAsSuperAdmin();
    $careItem = CareItem::factory()->create();

    $response = $this->actingAs($superAdmin)->delete(route('admin.care-items.destroy', $careItem));

    $response->assertRedirect(route('admin.care-items.index'));
    expect(CareItem::query()->whereKey($careItem->id)->exists())->toBeFalse();
});

test('manager cannot delete a care item', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $careItem = CareItem::factory()->create();

    $response = $this->actingAs($manager)->delete(route('admin.care-items.destroy', $careItem));

    $response->assertForbidden();
    expect(CareItem::query()->whereKey($careItem->id)->exists())->toBeTrue();
});
