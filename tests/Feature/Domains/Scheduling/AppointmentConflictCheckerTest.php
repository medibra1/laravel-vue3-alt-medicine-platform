<?php

use App\Domains\Patients\Models\Patient;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Services\AppointmentConflictChecker;
use Carbon\Carbon;

function makeAppointmentForConflictTest(Practitioner $practitioner, string $startsAt, int $durationMinutes, string $status = 'scheduled'): Appointment
{
    $center = $practitioner->center;
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);

    return Appointment::query()->create([
        'center_id' => $center->id,
        'practitioner_id' => $practitioner->id,
        'patient_id' => $patient->id,
        'starts_at' => $startsAt,
        'duration_minutes' => $durationMinutes,
        'status' => $status,
        'created_by' => $patient->created_by,
    ]);
}

test('detects a full overlap', function () {
    $practitioner = Practitioner::factory()->create();
    makeAppointmentForConflictTest($practitioner, '2026-09-10 10:00:00', 30);

    $checker = new AppointmentConflictChecker;

    expect($checker->hasConflict($practitioner->id, Carbon::parse('2026-09-10 10:00:00'), 30))->toBeTrue();
});

test('detects a partial overlap at the start', function () {
    $practitioner = Practitioner::factory()->create();
    makeAppointmentForConflictTest($practitioner, '2026-09-10 10:00:00', 30);

    $checker = new AppointmentConflictChecker;

    expect($checker->hasConflict($practitioner->id, Carbon::parse('2026-09-10 09:45:00'), 30))->toBeTrue();
});

test('detects a partial overlap at the end', function () {
    $practitioner = Practitioner::factory()->create();
    makeAppointmentForConflictTest($practitioner, '2026-09-10 10:00:00', 30);

    $checker = new AppointmentConflictChecker;

    expect($checker->hasConflict($practitioner->id, Carbon::parse('2026-09-10 10:15:00'), 30))->toBeTrue();
});

test('adjacent slots (end of A = start of B) do not conflict', function () {
    $practitioner = Practitioner::factory()->create();
    makeAppointmentForConflictTest($practitioner, '2026-09-10 10:00:00', 30);

    $checker = new AppointmentConflictChecker;

    expect($checker->hasConflict($practitioner->id, Carbon::parse('2026-09-10 10:30:00'), 30))->toBeFalse();
    expect($checker->hasConflict($practitioner->id, Carbon::parse('2026-09-10 09:30:00'), 30))->toBeFalse();
});

test('cancelled and no-show appointments are ignored', function () {
    $practitioner = Practitioner::factory()->create();
    makeAppointmentForConflictTest($practitioner, '2026-09-10 10:00:00', 30, 'cancelled');
    makeAppointmentForConflictTest($practitioner, '2026-09-10 10:00:00', 30, 'no_show');

    $checker = new AppointmentConflictChecker;

    expect($checker->hasConflict($practitioner->id, Carbon::parse('2026-09-10 10:00:00'), 30))->toBeFalse();
});

test('excludes the appointment itself when rescheduling', function () {
    $practitioner = Practitioner::factory()->create();
    $appointment = makeAppointmentForConflictTest($practitioner, '2026-09-10 10:00:00', 30);

    $checker = new AppointmentConflictChecker;

    expect($checker->hasConflict($practitioner->id, Carbon::parse('2026-09-10 10:00:00'), 30, $appointment->id))->toBeFalse();
    expect($checker->hasConflict($practitioner->id, Carbon::parse('2026-09-10 10:00:00'), 30))->toBeTrue();
});

test('a different practitioner at the same time never conflicts', function () {
    $practitionerA = Practitioner::factory()->create();
    $practitionerB = Practitioner::factory()->create();
    makeAppointmentForConflictTest($practitionerA, '2026-09-10 10:00:00', 30);

    $checker = new AppointmentConflictChecker;

    expect($checker->hasConflict($practitionerB->id, Carbon::parse('2026-09-10 10:00:00'), 30))->toBeFalse();
});
