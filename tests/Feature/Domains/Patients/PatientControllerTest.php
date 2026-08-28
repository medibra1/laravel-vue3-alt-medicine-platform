<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use Illuminate\Support\Str;

test('guests are redirected to login', function () {
    $this->get(route('admin.patients.index'))->assertRedirect(route('login'));
});

test('super admin can list patients from every center', function () {
    $superAdmin = actingAsSuperAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    Patient::factory()->for($centerA, 'center')->create();
    Patient::factory()->for($centerB, 'center')->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.patients.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['patients']['data'])->toHaveCount(2);
});

test('manager only sees patients from their own center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    Patient::factory()->for($ownCenter, 'center')->create();
    Patient::factory()->for($otherCenter, 'center')->create();

    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->get(route('admin.patients.index'));

    $response->assertOk();
    $data = $response->inertiaPage()['props']['patients']['data'];
    expect($data)->toHaveCount(1);
    expect($data[0]['intake_center_id'])->toBe($ownCenter->id);
});

test('super admin can create a draft patient with a minimal payload', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    $response = $this->actingAs($superAdmin)->postJson(route('admin.patients.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'intake_center_id' => $center->id,
    ]);

    $response->assertCreated();
    $patient = Patient::query()->findOrFail($response->json('id'));
    expect($patient->intake_center_id)->toBe($center->id);
    expect($patient->first_name)->toBeNull();
    expect($patient->latestStatus()->name)->toBe('draft');
});

test('patient_number auto-increments per intake center', function () {
    $superAdmin = actingAsSuperAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();

    $firstInA = $this->actingAs($superAdmin)->postJson(route('admin.patients.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'intake_center_id' => $centerA->id,
    ]);
    $secondInA = $this->actingAs($superAdmin)->postJson(route('admin.patients.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'intake_center_id' => $centerA->id,
    ]);
    $firstInB = $this->actingAs($superAdmin)->postJson(route('admin.patients.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'intake_center_id' => $centerB->id,
    ]);

    expect(Patient::query()->findOrFail($firstInA->json('id'))->patient_number)->toBe('0001');
    expect(Patient::query()->findOrFail($secondInA->json('id'))->patient_number)->toBe('0002');
    expect(Patient::query()->findOrFail($firstInB->json('id'))->patient_number)->toBe('0001');
});

test('manager creating a draft is scoped to their own center regardless of payload', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->postJson(route('admin.patients.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'intake_center_id' => $otherCenter->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('intake_center_id');
    expect(Patient::query()->count())->toBe(0);
});

test('manager can create a draft without sending intake_center_id', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->postJson(route('admin.patients.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
    ]);

    $response->assertCreated();
    $patient = Patient::query()->findOrFail($response->json('id'));
    expect($patient->intake_center_id)->toBe($ownCenter->id);
});

test('a non-super_admin can create a draft when intake_center_id is explicitly null in the payload', function () {
    // Regression: the real Vue form (useForm()) always submits every
    // field it knows about, even ones with no matching UI control for
    // this role — intake_center_id arrives as an explicit null, not an
    // omitted key. 'intake_center_id' => ['prohibited', 'integer',
    // 'exists:centers,id'] stacked in one array failed 'integer' against
    // that null before 'prohibited' was ever meaningfully evaluated — a
    // 422 that the previous test (which omits the key entirely, so
    // Laravel treats it as genuinely absent) never exercised. Found via
    // real browser testing as a practitioner, who has no center field on
    // this form at all (same as manager).
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->postJson(route('admin.patients.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'intake_center_id' => null,
    ]);

    $response->assertCreated();
    $patient = Patient::query()->findOrFail($response->json('id'));
    expect($patient->intake_center_id)->toBe($ownCenter->id);
});

test('replaying storeDraft with the same client_uuid is idempotent', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $uuid = (string) Str::uuid();

    $first = $this->actingAs($superAdmin)->postJson(route('admin.patients.draft.store'), [
        'client_uuid' => $uuid,
        'intake_center_id' => $center->id,
    ]);

    $second = $this->actingAs($superAdmin)->postJson(route('admin.patients.draft.store'), [
        'client_uuid' => $uuid,
        'intake_center_id' => $center->id,
    ]);

    $first->assertCreated();
    $second->assertOk();
    expect($second->json('id'))->toBe($first->json('id'));
    expect(Patient::query()->count())->toBe(1);
});

test('manager cannot update a draft patient from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $patient = Patient::factory()->for($otherCenter, 'center')->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->patchJson(route('admin.patients.draft.update', $patient), [
        'first_name' => 'Amina',
    ]);

    $response->assertForbidden();
});

test('manager can update a draft patient in their own center with a partial payload', function () {
    $ownCenter = Center::factory()->create();
    $patient = Patient::factory()->for($ownCenter, 'center')->create(['first_name' => null]);
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->patchJson(route('admin.patients.draft.update', $patient), [
        'first_name' => 'Amina',
    ]);

    $response->assertOk();
    expect($patient->fresh()->first_name)->toBe('Amina');
});

test('a malformed email is rejected even on a draft', function () {
    $superAdmin = actingAsSuperAdmin();
    $patient = Patient::factory()->create();

    $response = $this->actingAs($superAdmin)->patchJson(route('admin.patients.draft.update', $patient), [
        'email' => 'not-an-email',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('email');
});

test('confirming with missing required fields fails and status stays draft', function () {
    $superAdmin = actingAsSuperAdmin();
    $patient = Patient::factory()->create(['first_name' => null]);
    $patient->setStatus('draft');

    $response = $this->actingAs($superAdmin)->post(route('admin.patients.confirm', $patient), []);

    $response->assertSessionHasErrors('first_name');
    expect($patient->fresh()->latestStatus()->name)->toBe('draft');
});

test('confirming with complete data transitions the patient to confirmed', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $patient = Patient::factory()->for($center, 'center')->create();
    $patient->setStatus('draft');

    $response = $this->actingAs($superAdmin)->post(route('admin.patients.confirm', $patient), []);

    // Back to the patient's own file, not the index — confirming is
    // usually followed by adding the first treatment. ?tab=ongoing&open=treatment
    // auto-opens the treatment wizard, since this only ever fires once per
    // patient (draft -> confirmed).
    $response->assertRedirect(route('admin.patients.edit', [
        'patient' => $patient,
        'tab' => 'ongoing',
        'open' => 'treatment',
    ]));
    expect($patient->fresh()->latestStatus()->name)->toBe('confirmed');
});

test('confirming succeeds when the payload sends every field explicitly, including null intake_center_id', function () {
    // Regression: useResilientForm spreads the whole reactive `form` on
    // every submit — a manager/practitioner never sees an "Centre
    // d'accueil" field on PatientInfoForm.vue at all (only rendered
    // when centers.length), so confirmPatient() always sends an
    // explicit intake_center_id: null. prepareForValidation() used
    // $this->input($key, $fallback) inside array_filter(), whose default
    // only applies when the key is missing entirely — an explicit null
    // survived the merge and failed 'required' below, redirecting back
    // to the same create page with no visible error (found via real
    // browser testing as a manager: confirming looked like nothing
    // happened at all).
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $patient = Patient::factory()->for($center, 'center')->create();
    $patient->setStatus('draft');

    $response = $this->actingAs($superAdmin)->post(route('admin.patients.confirm', $patient), [
        'first_name' => null,
        'last_name' => null,
        'gender' => null,
        'phone' => null,
        'city' => null,
        'intake_center_id' => null,
    ]);

    $response->assertSessionHasNoErrors();
    expect($patient->fresh()->latestStatus()->name)->toBe('confirmed');
});

test('manager cannot confirm a patient from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $patient = Patient::factory()->for($otherCenter, 'center')->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.patients.confirm', $patient), []);

    $response->assertForbidden();
});

test('manager cannot delete a patient from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $patient = Patient::factory()->for($otherCenter, 'center')->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->delete(route('admin.patients.destroy', $patient));

    $response->assertForbidden();
    expect(Patient::query()->whereKey($patient->id)->exists())->toBeTrue();
});

test('super admin can delete any patient', function () {
    $superAdmin = actingAsSuperAdmin();
    $patient = Patient::factory()->create();

    $response = $this->actingAs($superAdmin)->delete(route('admin.patients.destroy', $patient));

    $response->assertRedirect(route('admin.patients.index'));
    expect(Patient::query()->whereKey($patient->id)->exists())->toBeFalse();
});
