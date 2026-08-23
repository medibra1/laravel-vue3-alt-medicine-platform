<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\CareItem;
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
    expect($treatment->diseases()->pluck('diseases.id')->sort()->values()->all())
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

test('a disease with tracked session progress cannot be removed from disease_ids, but a new one can be added', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $trackedDisease = Disease::factory()->create();
    $untrackedDisease = Disease::factory()->create();
    $newDisease = Disease::factory()->create();
    $treatment = Treatment::factory()->for($center, 'center')->create();
    $treatment->diseases()->sync([$trackedDisease->id, $untrackedDisease->id]);
    $treatment->setStatus('confirmed');
    $session = $treatment->sessions()->create(['session_date' => now(), 'created_by' => $superAdmin->id]);
    $session->diseaseProgress()->create(['disease_id' => $trackedDisease->id, 'outcome' => 'ongoing']);

    $removingTracked = $this->actingAs($superAdmin)->patchJson(
        route('admin.treatments.draft.update', $treatment),
        ['disease_ids' => [$untrackedDisease->id]],
    );

    $removingTracked->assertUnprocessable();
    $removingTracked->assertJsonValidationErrors('disease_ids');
    expect($treatment->diseases()->pluck('diseases.id')->sort()->values()->all())
        ->toBe(collect([$trackedDisease->id, $untrackedDisease->id])->sort()->values()->all());

    $addingNew = $this->actingAs($superAdmin)->patchJson(
        route('admin.treatments.draft.update', $treatment),
        ['disease_ids' => [$trackedDisease->id, $untrackedDisease->id, $newDisease->id]],
    );

    $addingNew->assertOk();
    expect($treatment->diseases()->pluck('diseases.id')->sort()->values()->all())
        ->toBe(collect([$trackedDisease->id, $untrackedDisease->id, $newDisease->id])->sort()->values()->all());
});

test('disease_ids stays freely editable while the treatment has no sessions yet', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $disease = Disease::factory()->create();
    $treatment = Treatment::factory()->for($center, 'center')->create();
    $treatment->diseases()->sync([$disease->id]);

    $response = $this->actingAs($superAdmin)->patchJson(
        route('admin.treatments.draft.update', $treatment),
        ['disease_ids' => []],
    );

    $response->assertOk();
    expect($treatment->diseases()->count())->toBe(0);
});

test('confirming with missing required fields fails and status stays draft', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create(['practitioner_id' => null, 'started_at' => null]);
    $treatment->setStatus('draft');

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.confirm', $treatment), []);

    $response->assertSessionHasErrors(['practitioner_id', 'started_at', 'disease_ids']);
    expect($treatment->fresh()->latestStatus()->name)->toBe('draft');
});

test('confirming with complete data transitions the treatment to ongoing', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center)->create();
    $treatment = Treatment::factory()->for($center, 'center')->for($practitioner, 'practitioner')->create();
    $treatment->setStatus('draft');
    $disease = Disease::factory()->create();
    $treatment->diseases()->sync([$disease->id]);

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.confirm', $treatment), []);

    // Back to the patient's file, not the flat treatments list — that's
    // where the natural next step (adding a session) happens. ?tab=ongoing
    // lands directly on the tab showing the treatment just confirmed.
    $response->assertRedirect(route('admin.patients.edit', ['patient' => $treatment->patient_id, 'tab' => 'ongoing']));
    // Confirming starts real-world follow-up immediately — no separate
    // manual step to reach `ongoing` (see Treatment::refreshClosureStatus()).
    expect($treatment->fresh()->latestStatus()->name)->toBe('ongoing');
});

test('confirming with only care_item_ids creates the implicit first session', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center)->create();
    $treatment = Treatment::factory()->for($center, 'center')->for($practitioner, 'practitioner')->create();
    $treatment->setStatus('draft');
    $disease = Disease::factory()->create();
    $treatment->diseases()->sync([$disease->id]);
    $careItem = CareItem::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.confirm', $treatment), [
        'care_item_ids' => [$careItem->id],
    ]);

    $response->assertRedirect(route('admin.patients.edit', ['patient' => $treatment->patient_id, 'tab' => 'ongoing']));
    $session = $treatment->fresh()->sessions()->first();
    expect($session)->not->toBeNull();
    expect($session->careItems()->pluck('care_items.id')->all())->toBe([$careItem->id]);
});

test('confirming with no care_item_ids creates no session', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center)->create();
    $treatment = Treatment::factory()->for($center, 'center')->for($practitioner, 'practitioner')->create();
    $treatment->setStatus('draft');
    $disease = Disease::factory()->create();
    $treatment->diseases()->sync([$disease->id]);

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.confirm', $treatment), []);

    $response->assertRedirect(route('admin.patients.edit', ['patient' => $treatment->patient_id, 'tab' => 'ongoing']));
    expect($treatment->fresh()->sessions()->count())->toBe(0);
});

test('confirming an already-confirmed treatment a second time does not create a second implicit session', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center)->create();
    $treatment = Treatment::factory()->for($center, 'center')->for($practitioner, 'practitioner')->create();
    $treatment->setStatus('draft');
    $disease = Disease::factory()->create();
    $treatment->diseases()->sync([$disease->id]);
    $firstCareItem = CareItem::factory()->create();
    $secondCareItem = CareItem::factory()->create();

    $this->actingAs($superAdmin)->post(route('admin.treatments.confirm', $treatment), [
        'care_item_ids' => [$firstCareItem->id],
    ]);
    expect($treatment->fresh()->sessions()->count())->toBe(1);

    // Re-submitting confirm() (e.g. re-opening and re-saving the wizard on
    // an already-started treatment) must not spawn a second implicit
    // session — care from here on only goes through
    // TreatmentSessionController, so this second care_item_ids payload is
    // silently ignored.
    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.confirm', $treatment), [
        'care_item_ids' => [$secondCareItem->id],
    ]);

    $response->assertRedirect(route('admin.patients.edit', ['patient' => $treatment->patient_id, 'tab' => 'ongoing']));
    $fresh = $treatment->fresh();
    expect($fresh->sessions()->count())->toBe(1);
    expect($fresh->sessions()->first()->careItems()->pluck('care_items.id')->all())->toBe([$firstCareItem->id]);
});

test('a patient with an ongoing treatment cannot start a new one', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $patient = Patient::factory()->create();
    $ongoing = Treatment::factory()->for($center, 'center')->for($patient)->create();
    $ongoing->setStatus('ongoing');

    $response = $this->actingAs($superAdmin)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'patient_id' => $patient->id,
        'center_id' => $center->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('patient_id');
});

test('a patient with only closed treatments can start a new one', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $patient = Patient::factory()->create();
    $closed = Treatment::factory()->for($center, 'center')->for($patient)->create();
    $closed->setStatus('ongoing');
    $closed->manualClose('closed_manually');

    $response = $this->actingAs($superAdmin)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'patient_id' => $patient->id,
        'center_id' => $center->id,
    ]);

    $response->assertCreated();
});

test('replaying storeDraft for an already-existing draft is not blocked by another ongoing treatment', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $patient = Patient::factory()->create();
    $uuid = (string) Str::uuid();

    $first = $this->actingAs($superAdmin)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => $uuid,
        'patient_id' => $patient->id,
        'center_id' => $center->id,
    ]);
    $first->assertCreated();

    // A second, unrelated treatment for the same patient turns ongoing in
    // between — the retry for the original draft's client_uuid must still
    // go through, it's the same treatment being re-saved, not a new one.
    $other = Treatment::factory()->for($center, 'center')->for($patient)->create();
    $other->setStatus('ongoing');

    $retry = $this->actingAs($superAdmin)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => $uuid,
        'patient_id' => $patient->id,
        'center_id' => $center->id,
    ]);

    $retry->assertOk();
    expect($retry->json('id'))->toBe($first->json('id'));
});

test('manager cannot confirm a treatment from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $treatment = Treatment::factory()->for($otherCenter, 'center')->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.treatments.confirm', $treatment), []);

    $response->assertForbidden();
});

test('super admin can manually close an ongoing treatment', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create();
    $treatment->setStatus('ongoing');

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.close', $treatment), [
        'closure_reason' => 'lost_to_follow_up',
    ]);

    $response->assertRedirect(route('admin.patients.edit', $treatment->patient_id));
    $fresh = $treatment->fresh();
    expect($fresh->latestStatus()->name)->toBe('closed');
    expect($fresh->closure_reason)->toBe('lost_to_follow_up');
});

test('super admin can manually close an ongoing treatment with protocol_not_followed', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create();
    $treatment->setStatus('ongoing');

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.close', $treatment), [
        'closure_reason' => 'protocol_not_followed',
    ]);

    $response->assertRedirect(route('admin.patients.edit', $treatment->patient_id));
    $fresh = $treatment->fresh();
    expect($fresh->latestStatus()->name)->toBe('closed');
    expect($fresh->closure_reason)->toBe('protocol_not_followed');
});

test('closing a treatment that is not ongoing fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create();
    $treatment->setStatus('draft');

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.close', $treatment), [
        'closure_reason' => 'closed_manually',
    ]);

    $response->assertSessionHasErrors('closure_reason');
    expect($treatment->fresh()->latestStatus()->name)->toBe('draft');
});

test('closure_reason "resolved" is rejected on the manual close endpoint', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create();
    $treatment->setStatus('ongoing');

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.close', $treatment), [
        'closure_reason' => 'resolved',
    ]);

    $response->assertSessionHasErrors('closure_reason');
});

test('manager cannot close a treatment from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $treatment = Treatment::factory()->for($otherCenter, 'center')->create();
    $treatment->setStatus('ongoing');
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.treatments.close', $treatment), [
        'closure_reason' => 'closed_manually',
    ]);

    $response->assertForbidden();
});

test('super admin can reopen a closed treatment', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create();
    $treatment->setStatus('ongoing');
    $treatment->manualClose('lost_to_follow_up');

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.reopen', $treatment));

    $response->assertRedirect(route('admin.patients.edit', $treatment->patient_id));
    $fresh = $treatment->fresh();
    expect($fresh->latestStatus()->name)->toBe('ongoing');
    expect($fresh->closure_reason)->toBeNull();
});

test('reopening a treatment that is not closed fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create();
    $treatment->setStatus('ongoing');

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.reopen', $treatment));

    $response->assertSessionHasErrors('status');
    expect($treatment->fresh()->latestStatus()->name)->toBe('ongoing');
});

test('manager cannot reopen a treatment from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $treatment = Treatment::factory()->for($otherCenter, 'center')->create();
    $treatment->setStatus('ongoing');
    $treatment->manualClose('closed_manually');
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.treatments.reopen', $treatment));

    $response->assertForbidden();
});

test('reopening a treatment re-blocks starting a new one for the same patient', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $patient = Patient::factory()->create();
    $treatment = Treatment::factory()->for($center, 'center')->for($patient)->create();
    $treatment->setStatus('ongoing');
    $treatment->manualClose('lost_to_follow_up');

    $this->actingAs($superAdmin)->post(route('admin.treatments.reopen', $treatment));

    $response = $this->actingAs($superAdmin)->postJson(route('admin.treatments.draft.store'), [
        'client_uuid' => (string) Str::uuid(),
        'patient_id' => $patient->id,
        'center_id' => $center->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('patient_id');
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
