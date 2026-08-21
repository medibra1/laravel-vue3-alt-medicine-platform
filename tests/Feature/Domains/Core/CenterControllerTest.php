<?php

use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Country;

test('guests are redirected to login', function () {
    $this->get(route('admin.centers.index'))->assertRedirect(route('login'));
});

test('manager cannot access the centers list', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->get(route('admin.centers.index'));

    $response->assertForbidden();
});

test('super admin can list centers', function () {
    $superAdmin = actingAsSuperAdmin();
    Center::factory()->count(2)->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.centers.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['centers']['data'])->toHaveCount(2);
});

test('super admin can create a center with an explicit code', function () {
    $superAdmin = actingAsSuperAdmin();
    $country = Country::factory()->create(['code' => '01']);

    $response = $this->actingAs($superAdmin)->post(route('admin.centers.store'), [
        'country_id' => $country->id,
        'code' => '05',
        'name' => 'Centre Abidjan',
    ]);

    $response->assertRedirect(route('admin.centers.index'));
    $center = Center::query()->where('country_id', $country->id)->firstOrFail();
    expect($center->code)->toBe('05');
    expect($center->name)->toBe('Centre Abidjan');
});

test('manager cannot create a center', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $country = Country::factory()->create();

    $response = $this->actingAs($manager)->post(route('admin.centers.store'), [
        'country_id' => $country->id,
        'code' => '05',
        'name' => 'Centre Test',
    ]);

    $response->assertForbidden();
    expect(Center::query()->where('country_id', $country->id)->count())->toBe(0);
});

test('creating a duplicate code within the same country fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    $country = Country::factory()->create();
    Center::factory()->for($country)->create(['code' => '05']);

    $response = $this->actingAs($superAdmin)->post(route('admin.centers.store'), [
        'country_id' => $country->id,
        'code' => '05',
        'name' => 'Doublon',
    ]);

    $response->assertSessionHasErrors('code');
    expect(Center::query()->where('country_id', $country->id)->count())->toBe(1);
});

test('the same code is allowed in a different country', function () {
    $superAdmin = actingAsSuperAdmin();
    $countryA = Country::factory()->create();
    $countryB = Country::factory()->create();
    Center::factory()->for($countryA)->create(['code' => '05']);

    $response = $this->actingAs($superAdmin)->post(route('admin.centers.store'), [
        'country_id' => $countryB->id,
        'code' => '05',
        'name' => 'Centre B',
    ]);

    $response->assertRedirect(route('admin.centers.index'));
    expect(Center::query()->where('country_id', $countryB->id)->count())->toBe(1);
});

test('next-code endpoint suggests the next free code for a country', function () {
    $superAdmin = actingAsSuperAdmin();
    $country = Country::factory()->create();
    Center::factory()->for($country)->create(['code' => '01']);
    Center::factory()->for($country)->create(['code' => '02']);

    $response = $this->actingAs($superAdmin)->getJson(
        route('admin.centers.next-code', ['country_id' => $country->id]),
    );

    $response->assertOk();
    expect($response->json('code'))->toBe('03');
});

test('super admin can update a center', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create(['name' => 'Ancien nom']);

    $response = $this->actingAs($superAdmin)->put(route('admin.centers.update', $center), [
        'country_id' => $center->country_id,
        'code' => $center->code,
        'name' => 'Nouveau nom',
    ]);

    $response->assertRedirect(route('admin.centers.index'));
    expect($center->fresh()->name)->toBe('Nouveau nom');
});

test('super admin can delete a center', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    $response = $this->actingAs($superAdmin)->delete(route('admin.centers.destroy', $center));

    $response->assertRedirect(route('admin.centers.index'));
    expect(Center::query()->whereKey($center->id)->exists())->toBeFalse();
});

test('manager cannot delete a center', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->delete(route('admin.centers.destroy', $ownCenter));

    $response->assertForbidden();
    expect(Center::query()->whereKey($ownCenter->id)->exists())->toBeTrue();
});
