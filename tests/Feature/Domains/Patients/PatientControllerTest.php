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

    $response->assertRedirect(route('admin.patients.index'));
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
