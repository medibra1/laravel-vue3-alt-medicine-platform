<?php

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\CareItem;
use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Patients\Models\TreatmentSessionDiseaseProgress;

test('guests are redirected to login', function () {
    $treatment = Treatment::factory()->create();

    $this->post(route('admin.treatments.sessions.store', $treatment))
        ->assertRedirect(route('login'));
});

test('super admin can create a session with care items and disease progress', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $treatment = Treatment::factory()->for($center, 'center')->create();
    $disease = Disease::factory()->create();
    $treatment->diseases()->sync([$disease->id]);
    $careItem = CareItem::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.sessions.store', $treatment), [
        'session_date' => '2026-08-20',
        'care_item_ids' => [$careItem->id],
        'disease_progress' => [
            ['disease_id' => $disease->id, 'outcome' => 'ongoing', 'notes' => 'Amélioration légère'],
        ],
    ]);

    $response->assertRedirect(route('admin.patients.edit', $treatment->patient_id));
    $session = $treatment->sessions()->firstOrFail();
    expect($session->careItems()->pluck('care_items.id')->all())->toBe([$careItem->id]);
    expect($session->diseaseProgress()->where('disease_id', $disease->id)->first()->outcome)->toBe('ongoing');
});

test('outcome_percentage is required when outcome is percentage', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create();
    $disease = Disease::factory()->create();
    $treatment->diseases()->sync([$disease->id]);

    $response = $this->actingAs($superAdmin)->post(route('admin.treatments.sessions.store', $treatment), [
        'session_date' => '2026-08-20',
        'disease_progress' => [
            ['disease_id' => $disease->id, 'outcome' => 'percentage'],
        ],
    ]);

    $response->assertSessionHasErrors('disease_progress.0.outcome_percentage');
});

test('updating a session upserts disease progress instead of duplicating it', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create();
    $disease = Disease::factory()->create();
    $treatment->diseases()->sync([$disease->id]);

    $first = $this->actingAs($superAdmin)->post(route('admin.treatments.sessions.store', $treatment), [
        'session_date' => '2026-08-20',
        'disease_progress' => [
            ['disease_id' => $disease->id, 'outcome' => 'ongoing'],
        ],
    ]);
    $first->assertRedirect();
    $session = $treatment->sessions()->firstOrFail();

    $this->actingAs($superAdmin)->patch(route('admin.treatments.sessions.update', [$treatment, $session]), [
        'session_date' => '2026-08-20',
        'disease_progress' => [
            ['disease_id' => $disease->id, 'outcome' => 'cured'],
        ],
    ]);

    expect(TreatmentSessionDiseaseProgress::query()->where('treatment_session_id', $session->id)->count())->toBe(1);
    expect($session->diseaseProgress()->first()->outcome)->toBe('cured');
});

test('a session that resolves the last unresolved disease auto-closes the treatment', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $treatment = Treatment::factory()->for($center, 'center')->create();
    $treatment->setStatus('ongoing');
    $diseaseA = Disease::factory()->create();
    $diseaseB = Disease::factory()->create();
    $treatment->diseases()->sync([$diseaseA->id, $diseaseB->id]);

    // First session resolves only one of the two diseases — still ongoing.
    $this->actingAs($superAdmin)->post(route('admin.treatments.sessions.store', $treatment), [
        'session_date' => '2026-08-20',
        'disease_progress' => [
            ['disease_id' => $diseaseA->id, 'outcome' => 'cured'],
        ],
    ]);
    expect($treatment->fresh()->latestStatus()->name)->toBe('ongoing');

    // Second session resolves the last one — the treatment auto-closes.
    $this->actingAs($superAdmin)->post(route('admin.treatments.sessions.store', $treatment), [
        'session_date' => '2026-08-21',
        'disease_progress' => [
            ['disease_id' => $diseaseB->id, 'outcome' => 'not_cured'],
        ],
    ]);
    $fresh = $treatment->fresh();
    expect($fresh->latestStatus()->name)->toBe('closed');
    expect($fresh->closure_reason)->toBe('resolved');
});

test('a session marking a disease as still ongoing does not close the treatment', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create();
    $treatment->setStatus('ongoing');
    $disease = Disease::factory()->create();
    $treatment->diseases()->sync([$disease->id]);

    $this->actingAs($superAdmin)->post(route('admin.treatments.sessions.store', $treatment), [
        'session_date' => '2026-08-20',
        'disease_progress' => [
            ['disease_id' => $disease->id, 'outcome' => 'ongoing'],
        ],
    ]);

    expect($treatment->fresh()->latestStatus()->name)->toBe('ongoing');
});

test('manager cannot create a session on a treatment from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $treatment = Treatment::factory()->for($otherCenter, 'center')->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.treatments.sessions.store', $treatment), [
        'session_date' => '2026-08-20',
    ]);

    $response->assertForbidden();
});

test('manager can create a session on a treatment in their own center', function () {
    $ownCenter = Center::factory()->create();
    $treatment = Treatment::factory()->for($ownCenter, 'center')->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.treatments.sessions.store', $treatment), [
        'session_date' => '2026-08-20',
    ]);

    $response->assertRedirect();
    expect($treatment->sessions()->count())->toBe(1);
});

test('manager cannot delete a session from a treatment in another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $treatment = Treatment::factory()->for($otherCenter, 'center')->create();
    $session = $treatment->sessions()->create(['session_date' => '2026-08-20', 'created_by' => User::factory()->create()->id]);
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->delete(route('admin.treatments.sessions.destroy', [$treatment, $session]));

    $response->assertForbidden();
    expect($treatment->sessions()->whereKey($session->id)->exists())->toBeTrue();
});

test('super admin can delete a session', function () {
    $superAdmin = actingAsSuperAdmin();
    $treatment = Treatment::factory()->create();
    $session = $treatment->sessions()->create(['session_date' => '2026-08-20', 'created_by' => $superAdmin->id]);

    $response = $this->actingAs($superAdmin)->delete(route('admin.treatments.sessions.destroy', [$treatment, $session]));

    $response->assertRedirect();
    expect($treatment->sessions()->whereKey($session->id)->exists())->toBeFalse();
});
