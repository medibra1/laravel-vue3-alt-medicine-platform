<?php

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;

test('accessibleCenterIds returns an empty array for a user with no practitioner role', function () {
    $user = User::factory()->create();

    expect($user->accessibleCenterIds())->toBe([]);
});

test('accessibleCenterIds returns a single center for a single-center practitioner', function () {
    $center = Center::factory()->create();
    $user = actingAsPractitionerOf($center);

    expect($user->accessibleCenterIds())->toBe([$center->id]);
});

test('accessibleCenterIds returns every center for a multi-center practitioner, sorted', function () {
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    $centerC = Center::factory()->create();
    $user = actingAsPractitionerOf($centerA, $centerB, $centerC);

    $ids = $user->accessibleCenterIds();

    expect($ids)->toHaveCount(3);
    expect($ids)->toEqual(collect([$centerA->id, $centerB->id, $centerC->id])->sort()->values()->all());
});

test('EnsureCenterAccess auto-selects the only accessible center for a single-center practitioner', function () {
    $center = Center::factory()->create();
    $user = actingAsPractitionerOf($center);

    $response = $this->actingAs($user)->get(route('admin.patients.index'));

    $response->assertOk();
    expect(session('active_center_id'))->toBe($center->id);
});

test('EnsureCenterAccess auto-selects the first accessible center when a multi-center practitioner has no session yet', function () {
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    $user = actingAsPractitionerOf($centerA, $centerB);

    $response = $this->actingAs($user)->get(route('admin.patients.index'));

    $response->assertOk();
    $expectedFirst = collect([$centerA->id, $centerB->id])->sort()->first();
    expect(session('active_center_id'))->toBe($expectedFirst);
});

test('EnsureCenterAccess respects an already-set valid session selection', function () {
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    $user = actingAsPractitionerOf($centerA, $centerB);

    $this->withSession(['active_center_id' => $centerB->id])
        ->actingAs($user)
        ->get(route('admin.patients.index'))
        ->assertOk();

    expect(session('active_center_id'))->toBe($centerB->id);
});

test('EnsureCenterAccess falls back to auto-select if the session center is no longer accessible', function () {
    $centerA = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $user = actingAsPractitionerOf($centerA);

    $this->withSession(['active_center_id' => $otherCenter->id])
        ->actingAs($user)
        ->get(route('admin.patients.index'))
        ->assertOk();

    expect(session('active_center_id'))->toBe($centerA->id);
});

test('a practitioner cannot see patients from a center they are not active on', function () {
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    $user = actingAsPractitionerOf($centerA, $centerB);

    $patientInB = Patient::factory()->create(['intake_center_id' => $centerB->id]);
    $patientInB->setStatus('confirmed');

    // Active center forced to A via session.
    $response = $this->withSession(['active_center_id' => $centerA->id])
        ->actingAs($user)
        ->get(route('admin.patients.edit', $patientInB));

    $response->assertForbidden();
});

test('a practitioner can view a patient from their active center but not update it', function () {
    $center = Center::factory()->create();
    $user = actingAsPractitionerOf($center);

    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    $patient->setStatus('confirmed');

    $response = $this->actingAs($user)->get(route('admin.patients.edit', $patient));

    $response->assertOk();
    expect($response->inertiaPage()['props']['can_update'])->toBeFalse();
});

test('ActiveCenterController rejects switching to a center the practitioner cannot access', function () {
    $center = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $user = actingAsPractitionerOf($center);

    $response = $this->actingAs($user)->post(route('admin.active-center.update'), [
        'center_id' => $otherCenter->id,
    ]);

    $response->assertSessionHasErrors('center_id');
});

test('ActiveCenterController lets a practitioner switch between their accessible centers', function () {
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    $user = actingAsPractitionerOf($centerA, $centerB);

    $response = $this->actingAs($user)->post(route('admin.active-center.update'), [
        'center_id' => $centerB->id,
    ]);

    $response->assertRedirect();
    expect(session('active_center_id'))->toBe($centerB->id);
});
