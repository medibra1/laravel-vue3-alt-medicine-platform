<?php

use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Models\Center;
use Illuminate\Support\Facades\Cache;

test('guests are redirected to login', function () {
    $this->get(route('admin.enum-options.index'))->assertRedirect(route('login'));
});

test('manager cannot access the enum options list', function () {
    $manager = actingAsManagerOf(Center::factory()->create());

    $response = $this->actingAs($manager)->get(route('admin.enum-options.index'));

    $response->assertForbidden();
});

test('super admin can list enum options', function () {
    $superAdmin = actingAsSuperAdmin();
    EnumOption::factory()->count(2)->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.enum-options.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['options']['data'])->toHaveCount(2);
});

test('super admin can create an enum option', function () {
    $superAdmin = actingAsSuperAdmin();

    $response = $this->actingAs($superAdmin)->post(route('admin.enum-options.store'), [
        'enum_type' => 'expense.category',
        'code' => 'RENT',
        'label' => ['fr' => 'Loyer', 'en' => 'Rent'],
        'order' => 1,
    ]);

    $response->assertRedirect(route('admin.enum-options.index'));
    $option = EnumOption::query()->where('enum_type', 'expense.category')->where('code', 'RENT')->firstOrFail();
    expect($option->label)->toBe(['fr' => 'Loyer', 'en' => 'Rent']);
});

test('manager cannot create an enum option', function () {
    $manager = actingAsManagerOf(Center::factory()->create());

    $response = $this->actingAs($manager)->post(route('admin.enum-options.store'), [
        'enum_type' => 'expense.category',
        'code' => 'RENT',
        'label' => ['fr' => 'Loyer', 'en' => 'Rent'],
    ]);

    $response->assertForbidden();
    expect(EnumOption::query()->where('code', 'RENT')->count())->toBe(0);
});

test('creating a duplicate code within the same enum_type fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    EnumOption::factory()->create(['enum_type' => 'expense.category', 'code' => 'RENT']);

    $response = $this->actingAs($superAdmin)->post(route('admin.enum-options.store'), [
        'enum_type' => 'expense.category',
        'code' => 'RENT',
        'label' => ['fr' => 'Doublon', 'en' => 'Duplicate'],
    ]);

    $response->assertSessionHasErrors('code');
    expect(EnumOption::query()->where('enum_type', 'expense.category')->count())->toBe(1);
});

test('the same code is allowed in a different enum_type', function () {
    $superAdmin = actingAsSuperAdmin();
    EnumOption::factory()->create(['enum_type' => 'expense.category', 'code' => 'OTHER']);

    $response = $this->actingAs($superAdmin)->post(route('admin.enum-options.store'), [
        'enum_type' => 'payroll_organism.type',
        'code' => 'OTHER',
        'label' => ['fr' => 'Autre', 'en' => 'Other'],
    ]);

    $response->assertRedirect(route('admin.enum-options.index'));
    expect(EnumOption::query()->where('enum_type', 'payroll_organism.type')->where('code', 'OTHER')->count())->toBe(1);
});

test('super admin can update an enum option', function () {
    $superAdmin = actingAsSuperAdmin();
    $option = EnumOption::factory()->create(['label' => ['fr' => 'Ancien', 'en' => 'Old']]);

    $response = $this->actingAs($superAdmin)->put(route('admin.enum-options.update', $option), [
        'enum_type' => $option->enum_type,
        'code' => $option->code,
        'label' => ['fr' => 'Nouveau', 'en' => 'New'],
    ]);

    $response->assertRedirect(route('admin.enum-options.index'));
    expect($option->fresh()->label)->toBe(['fr' => 'Nouveau', 'en' => 'New']);
});

test('super admin can delete an enum option', function () {
    $superAdmin = actingAsSuperAdmin();
    $option = EnumOption::factory()->create();

    $response = $this->actingAs($superAdmin)->delete(route('admin.enum-options.destroy', $option));

    $response->assertRedirect(route('admin.enum-options.index'));
    expect(EnumOption::query()->whereKey($option->id)->exists())->toBeFalse();
});

test('manager cannot delete an enum option', function () {
    $manager = actingAsManagerOf(Center::factory()->create());
    $option = EnumOption::factory()->create();

    $response = $this->actingAs($manager)->delete(route('admin.enum-options.destroy', $option));

    $response->assertForbidden();
    expect(EnumOption::query()->whereKey($option->id)->exists())->toBeTrue();
});

test('deleting an enum option flushes its type cache', function () {
    $superAdmin = actingAsSuperAdmin();
    $option = EnumOption::factory()->create(['enum_type' => 'disease_category.type', 'domain' => null]);

    EnumOption::cachedByType('disease_category.type');
    expect(Cache::has('enum_options:disease_category.type'))->toBeTrue();

    $this->actingAs($superAdmin)->delete(route('admin.enum-options.destroy', $option));

    expect(Cache::has('enum_options:disease_category.type'))->toBeFalse();
});
