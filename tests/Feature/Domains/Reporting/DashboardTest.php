<?php

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;

test('a manager sees the figures of their center only', function () {
    $center = Center::factory()->create();
    Patient::factory()->count(2)->create(['intake_center_id' => $center->id]);
    Patient::factory()->create(['intake_center_id' => Center::factory()->create()->id]);

    $this->actingAs(actingAsManagerOf($center))->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('Dashboard')
            ->where('stats.patients.total', 2)
            ->has('stats.months', 12)
            ->has('stats.patients.series', 12)
            ->has('stats.billing')
            ->missing('stats.centers'));
});

test('a global admin sees every center and can narrow to one', function () {
    $center = Center::factory()->create();
    Patient::factory()->count(2)->create(['intake_center_id' => $center->id]);
    Patient::factory()->create(['intake_center_id' => Center::factory()->create()->id]);
    $admin = actingAsSuperAdmin();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.patients.total', 3)->has('stats.centers', 2));

    $this->actingAs($admin)->get(route('dashboard', ['center_id' => $center->id]))
        ->assertInertia(fn ($page) => $page->where('stats.patients.total', 2)->where('selectedCenterId', $center->id));
});

test('blocks of a disabled module are left out', function () {
    $center = Center::factory()->create(['modules' => ['patients']]);

    $this->actingAs(actingAsManagerOf($center))->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('stats.patients')->missing('stats.billing')->missing('stats.appointments'));
});

test('an account attached to no center cannot open the dashboard', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertForbidden();
});
