<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\PractitionerTimeOff;

function timeOffPayload(array $overrides = []): array
{
    return [
        'starts_on' => '2026-10-12',
        'ends_on' => '2026-10-16',
        'reason' => 'vacation',
        ...$overrides,
    ];
}

test('a manager can create a time off for a practitioner of their center', function () {
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $manager = actingAsManagerOf($center);

    $this->actingAs($manager)
        ->post(route('admin.practitioners.time-offs.store', $practitioner), timeOffPayload())
        ->assertSessionHasNoErrors();

    $timeOff = PractitionerTimeOff::query()->where('practitioner_id', $practitioner->id)->sole();
    expect($timeOff->reason)->toBe('vacation');
    expect($timeOff->created_by)->toBe($manager->id);
});

test('a manager cannot create a time off for a practitioner of another center', function () {
    $ownCenter = Center::factory()->create();
    $practitioner = Practitioner::factory()->for(Center::factory()->create(), 'center')->create();
    $manager = actingAsManagerOf($ownCenter);

    $this->actingAs($manager)
        ->post(route('admin.practitioners.time-offs.store', $practitioner), timeOffPayload())
        ->assertSessionHasErrors('practitioner_id');

    expect(PractitionerTimeOff::query()->count())->toBe(0);
});

test('ends_on before starts_on is rejected', function () {
    $superAdmin = actingAsSuperAdmin();
    $practitioner = Practitioner::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.practitioners.time-offs.store', $practitioner), timeOffPayload(['ends_on' => '2026-10-10']))
        ->assertSessionHasErrors('ends_on');
});

test('an unknown reason is rejected', function () {
    $superAdmin = actingAsSuperAdmin();
    $practitioner = Practitioner::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.practitioners.time-offs.store', $practitioner), timeOffPayload(['reason' => 'holiday']))
        ->assertSessionHasErrors('reason');
});

test('creating a time off flashes the number of affected appointments without touching them', function () {
    $superAdmin = actingAsSuperAdmin();
    $practitioner = Practitioner::factory()->create();
    $patient = Patient::factory()->create(['intake_center_id' => $practitioner->center_id]);
    $make = fn (string $startsAt, string $status) => Appointment::query()->create([
        'center_id' => $practitioner->center_id,
        'practitioner_id' => $practitioner->id,
        'patient_id' => $patient->id,
        'starts_at' => $startsAt,
        'duration_minutes' => 30,
        'status' => $status,
        'created_by' => $superAdmin->id,
    ]);
    $inside = $make('2026-10-12 09:00:00', 'scheduled');
    $make('2026-10-16 17:30:00', 'confirmed'); // last (inclusive) day
    $make('2026-10-14 10:00:00', 'cancelled'); // not counted
    $make('2026-10-17 09:00:00', 'scheduled'); // after the period

    $this->actingAs($superAdmin)
        ->post(route('admin.practitioners.time-offs.store', $practitioner), timeOffPayload())
        ->assertSessionHas('time_off_affected_appointments', 2);

    expect($inside->fresh()->status)->toBe('scheduled');
    expect(Appointment::query()->count())->toBe(4);
});

test('a manager can delete a time off of their center but not of another', function () {
    $center = Center::factory()->create();
    $own = PractitionerTimeOff::query()->create([
        'practitioner_id' => Practitioner::factory()->for($center, 'center')->create()->id,
        'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12', 'created_by' => actingAsSuperAdmin()->id,
    ]);
    $foreign = PractitionerTimeOff::query()->create([
        'practitioner_id' => Practitioner::factory()->for(Center::factory()->create(), 'center')->create()->id,
        'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12', 'created_by' => $own->created_by,
    ]);
    $manager = actingAsManagerOf($center);

    $this->actingAs($manager)->delete(route('admin.time-offs.destroy', $foreign))->assertForbidden();
    $this->actingAs($manager)->delete(route('admin.time-offs.destroy', $own))->assertRedirect();

    expect(PractitionerTimeOff::query()->whereKey($own->id)->exists())->toBeFalse();
    expect(PractitionerTimeOff::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

test('index lists a practitioner time offs as JSON', function () {
    $superAdmin = actingAsSuperAdmin();
    $practitioner = Practitioner::factory()->create();
    PractitionerTimeOff::query()->create([
        'practitioner_id' => $practitioner->id,
        'starts_on' => '2026-10-12', 'ends_on' => '2026-10-13', 'reason' => 'training', 'created_by' => $superAdmin->id,
    ]);

    $this->actingAs($superAdmin)
        ->getJson(route('admin.practitioners.time-offs.index', $practitioner))
        ->assertOk()
        ->assertJsonPath('0.starts_on', '2026-10-12')
        ->assertJsonPath('0.reason', 'training');
});
