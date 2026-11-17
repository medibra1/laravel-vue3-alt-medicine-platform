<?php

use App\Domains\Core\Models\Center;
use App\Domains\Core\Support\Modules;

test('normalize keeps known modules and adds their dependencies', function () {
    expect(Modules::normalize(null))->toBeNull()
        ->and(Modules::normalize(['billing', 'unknown']))->toBe(['patients', 'billing'])
        ->and(Modules::normalize([]))->toBe([]);
});

test('a module switched off for the center blocks its routes', function () {
    $center = Center::factory()->create(['modules' => ['patients']]);
    $manager = actingAsManagerOf($center);

    $this->actingAs($manager)->get(route('admin.patients.index'))->assertOk();
    $this->actingAs($manager)->get(route('admin.agenda'))->assertForbidden();
    $this->actingAs($manager)->get(route('admin.treatments.index'))->assertForbidden();
    $this->actingAs($manager)->get(route('admin.billing.index'))->assertForbidden();
});

test('a user restriction narrows the modules of the center', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $manager->update(['modules' => ['patients']]);

    expect($manager->enabledModules($center->id))->toBe(['patients']);

    $this->actingAs($manager)->get(route('admin.patients.index'))->assertOk();
    $this->actingAs($manager)->get(route('admin.practitioners.index'))->assertForbidden();
});

test('a user restriction never grants a module the center does not have', function () {
    $center = Center::factory()->create(['modules' => ['patients']]);
    $manager = actingAsManagerOf($center);
    $manager->update(['modules' => ['patients', 'billing']]);

    expect($manager->enabledModules($center->id))->toBe(['patients']);
});

test('global admins always have every module', function () {
    $center = Center::factory()->create(['modules' => []]);

    expect(actingAsAdmin()->enabledModules($center->id))->toBe(Modules::all());
    $this->actingAs(actingAsSuperAdmin())->get(route('admin.agenda'))->assertOk();
});

test('enabled modules are shared with the frontend', function () {
    $center = Center::factory()->create(['modules' => ['patients', 'scheduling']]);

    $this->actingAs(actingAsManagerOf($center))->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.modules', ['patients', 'scheduling'])
            ->where('auth.is_manager', true)
            ->where('auth.is_global_admin', false)
            ->where('auth.active_center.id', $center->id));
});

test('an admin can open patients of any center', function () {
    $this->actingAs(actingAsAdmin())->get(route('admin.patients.index'))->assertOk();
});
