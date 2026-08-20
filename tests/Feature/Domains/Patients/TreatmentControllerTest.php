<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Support\Str;

test('guests are redirected to login', function () {
    $this->get(route('admin.treatments.index'))->assertRedirect(route('login'));
});

test('super admin can list treatments from every center', function () {
    $superAdmin = actingAsSuperAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    Treatment::factory()->for($centerA, 'center')->create();
    Treatment::factory()->for($centerB, 'center')->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.treatments.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['treatments']['data'])->toHaveCount(2);
});

test('manager only sees treatments from their own center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    Treatment::factory()->for($ownCenter, 'center')->create();
    Treatment::factory()->for($otherCenter, 'center')->create();

    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->get(route('admin.treatments.index'));

    $response->assertOk();
    $data = $response->inertiaPage()['props']['treatments']['data'];
    expect($data)->toHaveCount(1);
    expect($data[0]['center_id'])->toBe($ownCenter->id);
});

test('super admin can create a draft treatment with a minimal payload', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $patient = Patient::factory()->create();

    $response = $this->actingAs($superAdmin)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'patient_id' => $patient->id,
        'center_id' => $center->id,
    ]);

    $response->assertCreated();
    $treatment = Treatment::query()->findOrFail($response->json('id'));
    expect($treatment->center_id)->toBe($center->id);
    expect($treatment->practitioner_id)->toBeNull();
    expect($treatment->latestStatus()->name)->toBe('draft');
});

test('manager creating a draft is scoped to their own center regardless of payload', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $patient = Patient::factory()->create();

    $response = $this->actingAs($manager)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'patient_id' => $patient->id,
        'center_id' => $otherCenter->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('center_id');
    expect(Treatment::query()->count())->toBe(0);
});

test('manager can create a draft without sending center_id', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $patient = Patient::factory()->create();

    $response = $this->actingAs($manager)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'patient_id' => $patient->id,
    ]);

    $response->assertCreated();
    $treatment = Treatment::query()->findOrFail($response->json('id'));
    expect($treatment->center_id)->toBe($ownCenter->id);
});

test('creating a draft without a patient_id fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    $response = $this->actingAs($superAdmin)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'center_id' => $center->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('patient_id');
});

test('replaying storeDraft with the same client_uuid is idempotent', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $patient = Patient::factory()->create();
    $uuid = (string) Str::uuid();

    $first = $this->actingAs($superAdmin)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => $uuid,
        'patient_id' => $patient->id,
        'center_id' => $center->id,
    ]);

    $second = $this->actingAs($superAdmin)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => $uuid,
        'patient_id' => $patient->id,
        'center_id' => $center->id,
    ]);

    $first->assertCreated();
    $second->assertOk();
    expect($second->json('id'))->toBe($first->json('id'));
    expect(Treatment::query()->count())->toBe(1);
});

test('storeDraft syncs the submitted disease_ids', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $patient = Patient::factory()->create();
    $diseases = Disease::factory()->count(2)->create();

    $response = $this->actingAs($superAdmin)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'patient_id' => $patient->id,
        'center_id' => $center->id,
        'disease_ids' => $diseases->pluck('id')->all(),
    ]);

    $response->assertCreated();
    $treatment = Treatment::query()->findOrFail($response->json('id'));
    expect($treatment->diseases()->pluck('patients_diseases.id')->sort()->values()->all())
        ->toBe($diseases->pluck('id')->sort()->values()->all());
});

test('manager cannot update a draft treatment from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $treatment = Treatment::factory()->for($otherCenter, 'center')->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->patchJson(route('admin.treatments.draft.update', $treatment), [
        'notes' => 'hello',
    ]);

    $response->assertForbidden();
});

test('manager can update a draft treatment in their own center with a partial payload', function () {
    $ownCenter = Center::factory()->create();
    $treatment = Treatment::factory()->for($ownCenter, 'center')->create(['notes' => null]);
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->patchJson(route('admin.treatments.draft.update', $treatment), [
        'notes' => 'Séance bien passée',
    ]);

    $response->assertOk();
    expect($treatment->fresh()->notes)->toBe('Séance bien passée');
});

test('confirming with missing required fields fails and status stays draft', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create(['practitioner_id' => null, 'started_at' => null]);
    $treatment->setStatus('draft');

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.confirm', $treatment), []);

    $response->assertSessionHasErrors(['practitioner_id', 'started_at', 'disease_ids']);
    expect($treatment->fresh()->latestStatus()->name)->toBe('draft');
});

test('confirming with complete data transitions the treatment to confirmed', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center)->create();
    $treatment = Treatment::factory()->for($center, 'center')->for($practitioner, 'practitioner')->create();
    $treatment->setStatus('draft');
    $disease = Disease::factory()->create();
    $treatment->diseases()->sync([$disease->id]);

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.confirm', $treatment), []);

    $response->assertRedirect(route('admin.treatments.index'));
    expect($treatment->fresh()->latestStatus()->name)->toBe('confirmed');
});

test('manager cannot confirm a treatment from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $treatment = Treatment::factory()->for($otherCenter, 'center')->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.treatments.confirm', $treatment), []);

    $response->assertForbidden();
});

test('manager cannot delete a treatment from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $treatment = Treatment::factory()->for($otherCenter, 'center')->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->delete(route('admin.treatments.destroy', $treatment));

    $response->assertForbidden();
    expect(Treatment::query()->whereKey($treatment->id)->exists())->toBeTrue();
});

test('super admin can delete any treatment', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create();

    $response = $this->actingAs($superAdmin)->delete(route('admin.treatments.destroy', $treatment));

    $response->assertRedirect(route('admin.treatments.index'));
    expect(Treatment::query()->whereKey($treatment->id)->exists())->toBeFalse();
});
