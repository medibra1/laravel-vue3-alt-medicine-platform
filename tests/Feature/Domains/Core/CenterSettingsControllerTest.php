<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\CareItem;

function settingsPayload(array $overrides = []): array
{
    return [
        'phone' => '0102030405',
        'email' => 'centre@example.com',
        'address' => '1 rue de la Paix',
        'currency' => 'xof',
        'timezone' => 'Africa/Abidjan',
        'settings' => [
            'session_price_in_person' => 15000,
            'session_price_remote' => 10000,
            'default_session_duration' => 45,
            'default_modality' => 'remote',
        ],
        ...$overrides,
    ];
}

test('a manager edits the settings of their own center', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $careItem = CareItem::factory()->create();

    $this->actingAs($manager)->get(route('admin.centers.settings.edit', $center))
        ->assertInertia(fn ($page) => $page->component('Admin/Centers/Settings')->where('canEditModules', false));

    $this->actingAs($manager)->put(route('admin.centers.settings.update', $center), settingsPayload([
        'care_prices' => [['care_item_id' => $careItem->id, 'price' => 2500]],
    ]))->assertRedirect();

    $center->refresh();

    expect($center->currency)->toBe('XOF')
        ->and($center->timezone)->toBe('Africa/Abidjan')
        ->and($center->setting('session_price_remote'))->toEqual(10000)
        ->and((float) $center->careItemPrices()->firstOrFail()->price)->toBe(2500.0);
});

test('an emptied care price is removed', function () {
    $center = Center::factory()->create();
    $careItem = CareItem::factory()->create();
    $center->careItemPrices()->create(['care_item_id' => $careItem->id, 'price' => 10]);

    $this->actingAs(actingAsManagerOf($center))->put(route('admin.centers.settings.update', $center), settingsPayload([
        'care_prices' => [['care_item_id' => $careItem->id, 'price' => null]],
    ]))->assertRedirect();

    expect($center->careItemPrices()->count())->toBe(0);
});

test('a manager cannot touch another center nor change modules', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);

    $this->actingAs($manager)->put(route('admin.centers.settings.update', Center::factory()->create()), settingsPayload())->assertForbidden();

    $this->actingAs($manager)->put(route('admin.centers.settings.update', $center), settingsPayload(['modules' => ['patients']]));
    expect($center->refresh()->modules)->toBeNull();
});

test('an admin sets the modules of a center, dependencies included', function () {
    $center = Center::factory()->create();

    $this->actingAs(actingAsAdmin())->put(route('admin.centers.settings.update', $center), settingsPayload(['modules' => ['billing']]))
        ->assertRedirect();

    expect($center->refresh()->modules)->toBe(['patients', 'billing']);
});

test('the manager shortcut redirects to the active center', function () {
    $center = Center::factory()->create();

    $this->actingAs(actingAsManagerOf($center))->get(route('admin.center-settings'))
        ->assertRedirect(route('admin.centers.settings.edit', $center));
});
