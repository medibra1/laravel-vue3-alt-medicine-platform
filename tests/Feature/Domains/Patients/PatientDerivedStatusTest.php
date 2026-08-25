<?php

use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;

test('a patient with no treatment is "new"', function () {
    $patient = Patient::factory()->create();

    expect($patient->derivedStatus())->toBe(['key' => 'new', 'label' => 'Nouveau', 'color' => 'secondary']);
});

test('a patient whose latest treatment is draft/confirmed/ongoing is "active"', function (string $status) {
    $patient = Patient::factory()->create();
    $treatment = Treatment::factory()->for($patient)->create();
    $treatment->setStatus($status);

    expect($patient->fresh()->derivedStatus())->toBe(['key' => 'active', 'label' => 'Actif', 'color' => 'success']);
})->with(['draft', 'confirmed', 'ongoing']);

test('a patient whose latest treatment closed as resolved is "completed"', function () {
    $patient = Patient::factory()->create();
    $treatment = Treatment::factory()->for($patient)->create(['closure_reason' => 'resolved']);
    $treatment->setStatus('closed');

    expect($patient->fresh()->derivedStatus())->toBe(['key' => 'completed', 'label' => 'Terminé', 'color' => 'info']);
});

test('a patient whose latest treatment closed as lost_to_follow_up is "unreachable"', function () {
    $patient = Patient::factory()->create();
    $treatment = Treatment::factory()->for($patient)->create(['closure_reason' => 'lost_to_follow_up']);
    $treatment->setStatus('closed');

    expect($patient->fresh()->derivedStatus())->toBe(['key' => 'unreachable', 'label' => 'Injoignable', 'color' => 'warning']);
});

test('a patient whose latest treatment closed as protocol_not_followed is "stopped"', function () {
    $patient = Patient::factory()->create();
    $treatment = Treatment::factory()->for($patient)->create(['closure_reason' => 'protocol_not_followed']);
    $treatment->setStatus('closed');

    expect($patient->fresh()->derivedStatus())->toBe(['key' => 'stopped', 'label' => 'Arrêté', 'color' => 'error']);
});

test('a patient whose latest treatment closed as closed_manually is "other"', function () {
    $patient = Patient::factory()->create();
    $treatment = Treatment::factory()->for($patient)->create(['closure_reason' => 'closed_manually']);
    $treatment->setStatus('closed');

    expect($patient->fresh()->derivedStatus())->toBe(['key' => 'other', 'label' => 'Autre', 'color' => 'secondary']);
});

test('derivedStatus() uses the most recent treatment, not the first one created', function () {
    $patient = Patient::factory()->create();
    $older = Treatment::factory()->for($patient)->create(['started_at' => '2026-01-01', 'closure_reason' => 'resolved']);
    $older->setStatus('closed');
    $newer = Treatment::factory()->for($patient)->create(['started_at' => '2026-06-01']);
    $newer->setStatus('ongoing');

    expect($patient->fresh()->derivedStatus())->toBe(['key' => 'active', 'label' => 'Actif', 'color' => 'success']);
});
