<?php

use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\CenterClosure;
use App\Domains\Patients\Models\Patient;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\PractitionerAvailability;
use App\Domains\Scheduling\Models\PractitionerTimeOff;

test('guests are redirected to login', function () {
    $this->get(route('admin.appointments.index'))
        ->assertRedirect(route('login'));
});

test('the agenda page renders for an authorized user', function () {
    $superAdmin = actingAsSuperAdmin();

    $response = $this->actingAs($superAdmin)->get(route('admin.agenda'));

    $response->assertOk();
});

test('super admin can create an appointment', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);

    $response = $this->actingAs($superAdmin)->post(route('admin.appointments.store'), [
        'center_id' => $center->id,
        'patient_id' => $patient->id,
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'modality' => 'in_person',
        'reason' => 'Suivi',
    ]);

    $response->assertRedirect(route('admin.patients.edit', $patient->id));
    expect(Appointment::query()->where('patient_id', $patient->id)->exists())->toBeTrue();
    $appointment = Appointment::query()->where('patient_id', $patient->id)->first();
    expect($appointment->status)->toBe('scheduled');
});

test('creating an appointment is rejected on a conflicting slot', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    Appointment::query()->create([
        'center_id' => $center->id,
        'practitioner_id' => $practitioner->id,
        'patient_id' => $patient->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'status' => 'scheduled',
        'created_by' => $superAdmin->id,
    ]);

    $response = $this->actingAs($superAdmin)->post(route('admin.appointments.store'), [
        'center_id' => $center->id,
        'patient_id' => $patient->id,
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-10 10:15:00',
        'duration_minutes' => 30,
        'modality' => 'in_person',
    ]);

    $response->assertSessionHasErrors('starts_at');
});

test('rescheduling is rejected when it conflicts with another appointment', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    Appointment::query()->create([
        'center_id' => $center->id,
        'practitioner_id' => $practitioner->id,
        'patient_id' => $patient->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'status' => 'scheduled',
        'created_by' => $superAdmin->id,
    ]);
    $toReschedule = Appointment::query()->create([
        'center_id' => $center->id,
        'practitioner_id' => $practitioner->id,
        'patient_id' => $patient->id,
        'starts_at' => '2026-09-10 14:00:00',
        'duration_minutes' => 30,
        'status' => 'scheduled',
        'created_by' => $superAdmin->id,
    ]);

    $response = $this->actingAs($superAdmin)->put(route('admin.appointments.update', $toReschedule), [
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'modality' => 'in_person',
    ]);

    $response->assertSessionHasErrors('starts_at');
});

test('rescheduling to the same slot the appointment already occupies is allowed', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    $appointment = Appointment::query()->create([
        'center_id' => $center->id,
        'practitioner_id' => $practitioner->id,
        'patient_id' => $patient->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'status' => 'scheduled',
        'created_by' => $superAdmin->id,
    ]);

    $response = $this->actingAs($superAdmin)->put(route('admin.appointments.update', $appointment), [
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 45,
        'modality' => 'in_person',
        'reason' => 'Durée rallongée',
    ]);

    $response->assertRedirect();
    expect($appointment->fresh()->duration_minutes)->toBe(45);
});

test('a manager cannot create an appointment for a patient outside their own center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($otherCenter, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $otherCenter->id]);
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.appointments.store'), [
        'patient_id' => $patient->id,
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'modality' => 'in_person',
    ]);

    $response->assertSessionHasErrors('patient_id');
});

test('cancelling an appointment requires a reason', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    $appointment = Appointment::query()->create([
        'center_id' => $center->id,
        'practitioner_id' => $practitioner->id,
        'patient_id' => $patient->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'status' => 'scheduled',
        'created_by' => $superAdmin->id,
    ]);

    $response = $this->actingAs($superAdmin)->post(route('admin.appointments.cancel', $appointment), []);

    $response->assertSessionHasErrors('cancellation_reason');
    expect($appointment->fresh()->status)->toBe('scheduled');
});

test('cancelling an appointment with a reason succeeds', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    $appointment = Appointment::query()->create([
        'center_id' => $center->id,
        'practitioner_id' => $practitioner->id,
        'patient_id' => $patient->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'status' => 'scheduled',
        'created_by' => $superAdmin->id,
    ]);

    $response = $this->actingAs($superAdmin)->post(route('admin.appointments.cancel', $appointment), [
        'cancellation_reason' => 'Patient indisponible',
    ]);

    $response->assertRedirect();
    $fresh = $appointment->fresh();
    expect($fresh->status)->toBe('cancelled');
    expect($fresh->cancellation_reason)->toBe('Patient indisponible');
});

test('modality is required when creating an appointment', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);

    $response = $this->actingAs($superAdmin)->post(route('admin.appointments.store'), [
        'center_id' => $center->id,
        'patient_id' => $patient->id,
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
    ]);

    $response->assertSessionHasErrors('modality');
});

test('an invalid modality value is rejected', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);

    $response = $this->actingAs($superAdmin)->post(route('admin.appointments.store'), [
        'center_id' => $center->id,
        'patient_id' => $patient->id,
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'modality' => 'by_carrier_pigeon',
    ]);

    $response->assertSessionHasErrors('modality');
});

test('a meeting_link is accepted when the appointment is remote', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);

    $response = $this->actingAs($superAdmin)->post(route('admin.appointments.store'), [
        'center_id' => $center->id,
        'patient_id' => $patient->id,
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'modality' => 'remote',
        'meeting_link' => 'https://meet.example.com/abc-def',
    ]);

    $response->assertRedirect(route('admin.patients.edit', $patient->id));
    $appointment = Appointment::query()->where('patient_id', $patient->id)->firstOrFail();
    expect($appointment->modality)->toBe('remote');
    expect($appointment->meeting_link)->toBe('https://meet.example.com/abc-def');
});

test('a malformed meeting_link is rejected', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);

    $response = $this->actingAs($superAdmin)->post(route('admin.appointments.store'), [
        'center_id' => $center->id,
        'patient_id' => $patient->id,
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'modality' => 'remote',
        'meeting_link' => 'not-a-url',
    ]);

    $response->assertSessionHasErrors('meeting_link');
});

test('available-slots returns the free slots for a practitioner on a given day', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    PractitionerAvailability::query()->create([
        'practitioner_id' => $practitioner->id,
        'day_of_week' => 4,
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $response = $this->actingAs($superAdmin)->getJson(route('admin.appointments.available-slots', [
        'practitioner_id' => $practitioner->id,
        'date' => '2026-09-10',
        'duration_minutes' => 30,
    ]));

    $response->assertOk();
    expect($response->json('slots'))->toHaveCount(2);
});

test('index includes late appointments on the last requested day and excludes the next day', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    $base = [
        'center_id' => $center->id,
        'practitioner_id' => $practitioner->id,
        'patient_id' => $patient->id,
        'duration_minutes' => 30,
        'status' => 'scheduled',
        'created_by' => $superAdmin->id,
    ];
    $lateLastDay = Appointment::query()->create([...$base, 'starts_at' => '2026-10-11 23:00:00']);
    $nextDay = Appointment::query()->create([...$base, 'starts_at' => '2026-10-12 09:00:00']);

    // `to` is exclusive: the day after the last displayed day (Sunday 11th).
    $response = $this->actingAs($superAdmin)->getJson(route('admin.appointments.index', [
        'center_id' => $center->id,
        'from' => '2026-10-05',
        'to' => '2026-10-12',
    ]));

    $ids = collect($response->assertOk()->json())->pluck('id');
    expect($ids)->toContain($lateLastDay->id)->not->toContain($nextDay->id);
});

test('an appointment cannot be booked on a practitioner time off day', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    PractitionerTimeOff::query()->create([
        'practitioner_id' => $practitioner->id,
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-10',
        'created_by' => $superAdmin->id,
    ]);

    $this->actingAs($superAdmin)->post(route('admin.appointments.store'), [
        'center_id' => $center->id,
        'patient_id' => $patient->id,
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'modality' => 'in_person',
    ])->assertSessionHasErrors('starts_at');

    expect(Appointment::query()->count())->toBe(0);
});

test('an appointment booked before a time off is never removed when the time off is added', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);

    $this->actingAs($superAdmin)->post(route('admin.appointments.store'), [
        'center_id' => $center->id,
        'patient_id' => $patient->id,
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-10 10:00:00',
        'duration_minutes' => 30,
        'modality' => 'in_person',
    ])->assertSessionHasNoErrors();

    $this->actingAs($superAdmin)->post(route('admin.practitioners.time-offs.store', $practitioner), [
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-11',
    ])->assertSessionHas('time_off_affected_appointments', 1);

    expect(Appointment::query()->sole()->status)->toBe('scheduled');
});

test('a center closure blocks appointments for every practitioner of that center only', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $closure = fn () => CenterClosure::query()->create([
        'center_id' => $center->id,
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-10',
        'label' => 'Travaux',
        'created_by' => $superAdmin->id,
    ]);
    $closure();

    $book = function (Center $c) use ($superAdmin) {
        $practitioner = Practitioner::factory()->for($c, 'center')->create();
        $patient = Patient::factory()->create(['intake_center_id' => $c->id]);

        return $this->actingAs($superAdmin)->post(route('admin.appointments.store'), [
            'center_id' => $c->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $practitioner->id,
            'starts_at' => '2026-09-10 10:00:00',
            'duration_minutes' => 30,
            'modality' => 'in_person',
        ]);
    };

    $book($center)->assertSessionHasErrors('starts_at');
    $book($center)->assertSessionHasErrors('starts_at');
    $book($otherCenter)->assertSessionHasNoErrors();

    expect(Appointment::query()->pluck('center_id')->all())->toBe([$otherCenter->id]);
});
