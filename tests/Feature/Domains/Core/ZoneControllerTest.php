<?php

use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Zone;

test('guests are redirected to login', function () {
    $this->get(route('admin.zones.index'))->assertRedirect(route('login'));
});

test('manager cannot access the zones list', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->get(route('admin.zones.index'));

    $response->assertForbidden();
});

test('super admin can list zones', function () {
    $superAdmin = actingAsSuperAdmin();
    Zone::factory()->count(2)->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.zones.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['zones']['data'])->toHaveCount(2);
});

test('super admin can create a zone', function () {
    $superAdmin = actingAsSuperAdmin();

    $response = $this->actingAs($superAdmin)->post(route('admin.zones.store'), [
        'code' => 'TESTZONE',
        'name' => ['fr' => 'Zone test', 'en' => 'Test zone'],
        'order' => 3,
    ]);

    $response->assertRedirect(route('admin.zones.index'));
    $zone = Zone::query()->where('code', 'TESTZONE')->firstOrFail();
    expect($zone->getTranslation('name', 'fr'))->toBe('Zone test');
    expect($zone->getTranslation('name', 'en'))->toBe('Test zone');
    expect($zone->order)->toBe(3);
});

test('manager cannot create a zone', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.zones.store'), [
        'code' => 'TESTZONE',
        'name' => ['fr' => 'Zone test', 'en' => 'Test zone'],
    ]);

    $response->assertForbidden();
    expect(Zone::query()->where('code', 'TESTZONE')->count())->toBe(0);
});

test('creating a duplicate zone code fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    Zone::factory()->create(['code' => 'DUPZONE']);

    $response = $this->actingAs($superAdmin)->post(route('admin.zones.store'), [
        'code' => 'DUPZONE',
        'name' => ['fr' => 'Doublon', 'en' => 'Duplicate'],
    ]);

    $response->assertSessionHasErrors('code');
    expect(Zone::query()->where('code', 'DUPZONE')->count())->toBe(1);
});

test('super admin can update a zone', function () {
    $superAdmin = actingAsSuperAdmin();
    $zone = Zone::factory()->create(['name' => ['fr' => 'Ancien nom', 'en' => 'Old name']]);

    $response = $this->actingAs($superAdmin)->put(route('admin.zones.update', $zone), [
        'code' => $zone->code,
        'name' => ['fr' => 'Nouveau nom', 'en' => 'New name'],
        'order' => $zone->order,
    ]);

    $response->assertRedirect(route('admin.zones.index'));
    expect($zone->fresh()->getTranslation('name', 'fr'))->toBe('Nouveau nom');
});

test('super admin can delete a zone', function () {
    $superAdmin = actingAsSuperAdmin();
    $zone = Zone::factory()->create();

    $response = $this->actingAs($superAdmin)->delete(route('admin.zones.destroy', $zone));

    $response->assertRedirect(route('admin.zones.index'));
    expect(Zone::query()->whereKey($zone->id)->exists())->toBeFalse();
});

test('manager cannot delete a zone', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $zone = Zone::factory()->create();

    $response = $this->actingAs($manager)->delete(route('admin.zones.destroy', $zone));

    $response->assertForbidden();
    expect(Zone::query()->whereKey($zone->id)->exists())->toBeTrue();
});
