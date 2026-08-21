<?php

use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Country;
use App\Domains\Core\Models\Zone;

test('guests are redirected to login', function () {
    $this->get(route('admin.countries.index'))->assertRedirect(route('login'));
});

test('manager cannot access the countries list', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->get(route('admin.countries.index'));

    $response->assertForbidden();
});

test('super admin can list countries', function () {
    $superAdmin = actingAsSuperAdmin();
    Country::factory()->count(2)->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.countries.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['countries']['data'])->toHaveCount(2);
});

test('super admin can create a country with a zone', function () {
    $superAdmin = actingAsSuperAdmin();
    $zone = Zone::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.countries.store'), [
        'zone_id' => $zone->id,
        'code' => '99',
        'name' => ['fr' => 'Pays test', 'en' => 'Test country'],
    ]);

    $response->assertRedirect(route('admin.countries.index'));
    $country = Country::query()->where('code', '99')->firstOrFail();
    expect($country->zone_id)->toBe($zone->id);
    expect($country->getTranslation('name', 'fr'))->toBe('Pays test');
});

test('super admin can create a country without a zone', function () {
    $superAdmin = actingAsSuperAdmin();

    $response = $this->actingAs($superAdmin)->post(route('admin.countries.store'), [
        'zone_id' => null,
        'code' => '98',
        'name' => ['fr' => 'Pays sans zone', 'en' => 'Country without zone'],
    ]);

    $response->assertRedirect(route('admin.countries.index'));
    $country = Country::query()->where('code', '98')->firstOrFail();
    expect($country->zone_id)->toBeNull();
});

test('manager cannot create a country', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.countries.store'), [
        'code' => '99',
        'name' => ['fr' => 'Pays test', 'en' => 'Test country'],
    ]);

    $response->assertForbidden();
    expect(Country::query()->where('code', '99')->count())->toBe(0);
});

test('creating a duplicate country code fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    Country::factory()->create(['code' => '05']);

    $response = $this->actingAs($superAdmin)->post(route('admin.countries.store'), [
        'code' => '05',
        'name' => ['fr' => 'Doublon', 'en' => 'Duplicate'],
    ]);

    $response->assertSessionHasErrors('code');
    expect(Country::query()->where('code', '05')->count())->toBe(1);
});

test('super admin can update a country and reassign its zone', function () {
    $superAdmin = actingAsSuperAdmin();
    $country = Country::factory()->create(['zone_id' => null]);
    $zone = Zone::factory()->create();

    $response = $this->actingAs($superAdmin)->put(route('admin.countries.update', $country), [
        'zone_id' => $zone->id,
        'code' => $country->code,
        'name' => ['fr' => 'Nouveau nom', 'en' => 'New name'],
    ]);

    $response->assertRedirect(route('admin.countries.index'));
    expect($country->fresh()->zone_id)->toBe($zone->id);
    expect($country->fresh()->getTranslation('name', 'fr'))->toBe('Nouveau nom');
});

test('super admin can clear a country zone assignment', function () {
    $superAdmin = actingAsSuperAdmin();
    $zone = Zone::factory()->create();
    $country = Country::factory()->create(['zone_id' => $zone->id]);

    $response = $this->actingAs($superAdmin)->put(route('admin.countries.update', $country), [
        'zone_id' => null,
        'code' => $country->code,
        'name' => ['fr' => $country->getTranslation('name', 'fr'), 'en' => $country->getTranslation('name', 'en')],
    ]);

    $response->assertRedirect(route('admin.countries.index'));
    expect($country->fresh()->zone_id)->toBeNull();
});

test('super admin can delete a country', function () {
    $superAdmin = actingAsSuperAdmin();
    $country = Country::factory()->create();

    $response = $this->actingAs($superAdmin)->delete(route('admin.countries.destroy', $country));

    $response->assertRedirect(route('admin.countries.index'));
    expect(Country::query()->whereKey($country->id)->exists())->toBeFalse();
});

test('manager cannot delete a country', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $country = Country::factory()->create();

    $response = $this->actingAs($manager)->delete(route('admin.countries.destroy', $country));

    $response->assertForbidden();
    expect(Country::query()->whereKey($country->id)->exists())->toBeTrue();
});
