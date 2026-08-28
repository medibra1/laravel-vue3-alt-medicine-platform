<?php

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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

test('a practitioner can view and update a patient from their active center', function () {
    // Was read-only (can_update === false) until 2026-08-25 — a
    // practitioner needing to register/edit a patient had no way to.
    // RolePermissions::practitioner() now grants patients.create/update;
    // treatments stay read-only (see its docblock).
    $center = Center::factory()->create();
    $user = actingAsPractitionerOf($center);

    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    $patient->setStatus('confirmed');

    $response = $this->actingAs($user)->get(route('admin.patients.edit', $patient));

    $response->assertOk();
    expect($response->inertiaPage()['props']['can_update'])->toBeTrue();
});

test('a practitioner can create a patient in their active center', function () {
    $center = Center::factory()->create();
    $user = actingAsPractitionerOf($center);

    $response = $this->actingAs($user)->get(route('admin.patients.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['can_create'])->toBeTrue();

    $response = $this->actingAs($user)->postJson(route('admin.patients.draft.store'), [
        'client_uuid' => Str::uuid()->toString(),
    ]);

    $response->assertCreated();
    expect(Patient::query()->where('intake_center_id', $center->id)->exists())->toBeTrue();
});

test('a practitioner can create a treatment and log a session for a patient in their active center', function () {
    // Regression: RolePermissions::practitioner() was read-only on
    // treatments/treatment_sessions until 2026-08-26 — a practitioner
    // adding a patient hit a 403 on POST /admin/treatments/draft trying
    // to add that patient's treatment right after, found via real
    // browser testing.
    $center = Center::factory()->create();
    $user = actingAsPractitionerOf($center);
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    $patient->setStatus('confirmed');

    $draftResponse = $this->actingAs($user)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'patient_id' => $patient->id,
    ]);

    $draftResponse->assertCreated();
    $treatment = Treatment::query()->findOrFail($draftResponse->json('id'));
    expect($treatment->center_id)->toBe($center->id);

    $sessionResponse = $this->actingAs($user)->post(route('admin.treatments.sessions.store', $treatment), [
        'session_date' => '2026-08-26',
    ]);

    $sessionResponse->assertRedirect(route('admin.patients.edit', $patient->id));
    expect($treatment->sessions()->count())->toBe(1);
});

test('a practitioner role created before RolePermissions::practitioner() gained a permission gets resynced on the next grant', function () {
    // Regression: grantPractitionerAccessToCenter() used to only call
    // syncPermissions() inside `if (! $roleModel)` — a role created
    // before patients.create/update were added to RolePermissions::
    // practitioner() stayed stuck on its creation-time (read-only) set
    // forever, with nothing to ever revisit it. Found via a real account
    // that could view but not add patients despite the permission set
    // having since been extended.
    grantPatientPermissions();
    grantTreatmentPermissions();

    $center = Center::factory()->create();
    $staleRole = Role::query()->create([
        'name' => 'practitioner',
        'guard_name' => 'web',
        'team_id' => $center->id,
    ]);
    setPermissionsTeamId($center->id);
    $staleRole->syncPermissions(['patients.viewAny', 'patients.view']);
    setPermissionsTeamId(null);

    $superAdmin = actingAsSuperAdmin();
    // grant_access with 'existing' status is what actually calls
    // grantPractitionerAccessToCenter() on a role that may already
    // exist — a fresh practitioner (created here) already has an
    // account on another center, and 'store()' on a second center is
    // the auto-join path.
    $otherCenter = Center::factory()->create();
    Practitioner::factory()->for($otherCenter)->create([
        'email' => 'stale-role-practitioner@example.com',
        'user_id' => User::factory()->create(['email' => 'stale-role-practitioner@example.com']),
    ]);

    $response = $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'first_name' => 'Someone',
        'last_name' => 'Else',
        'center_id' => $center->id,
        'matricule' => '099',
        'email' => 'stale-role-practitioner@example.com',
        'grant_access' => true,
    ]);

    $response->assertRedirect(route('admin.practitioners.index'));

    setPermissionsTeamId($center->id);
    $user = User::query()->where('email', 'stale-role-practitioner@example.com')->firstOrFail();
    expect($user->can('patients.create'))->toBeTrue();
    expect($user->can('patients.update'))->toBeTrue();
    setPermissionsTeamId(null);
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
