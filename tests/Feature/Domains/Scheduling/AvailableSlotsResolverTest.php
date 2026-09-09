<?php

use App\Domains\Patients\Models\Patient;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\PractitionerAvailability;
use App\Domains\Scheduling\Services\AvailableSlotsResolver;
use Carbon\CarbonImmutable;

test('respects the practitioner\'s recurring availability window', function () {
    $practitioner = Practitioner::factory()->create();
    // 2026-09-10 is a Thursday (dayOfWeek = 4).
    PractitionerAvailability::query()->create([
        'practitioner_id' => $practitioner->id,
        'day_of_week' => 4,
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $slots = (new AvailableSlotsResolver)->resolve($practitioner->id, CarbonImmutable::parse('2026-09-10'), 30);

    expect($slots)->toHaveCount(2);
    expect($slots[0]['starts_at']->format('H:i'))->toBe('09:00');
    expect($slots[1]['starts_at']->format('H:i'))->toBe('09:30');
});

test('excludes slots already booked', function () {
    $practitioner = Practitioner::factory()->create();
    PractitionerAvailability::query()->create([
        'practitioner_id' => $practitioner->id,
        'day_of_week' => 4,
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);
    $patient = Patient::factory()->create(['intake_center_id' => $practitioner->center_id]);
    Appointment::query()->create([
        'center_id' => $practitioner->center_id,
        'practitioner_id' => $practitioner->id,
        'patient_id' => $patient->id,
        'starts_at' => '2026-09-10 09:30:00',
        'duration_minutes' => 30,
        'status' => 'scheduled',
        'created_by' => $patient->created_by,
    ]);

    $slots = (new AvailableSlotsResolver)->resolve($practitioner->id, CarbonImmutable::parse('2026-09-10'), 30);

    expect($slots)->toHaveCount(1);
    expect($slots[0]['starts_at']->format('H:i'))->toBe('09:00');
});

test('returns an empty array when the practitioner has no availability that day', function () {
    $practitioner = Practitioner::factory()->create();

    $slots = (new AvailableSlotsResolver)->resolve($practitioner->id, CarbonImmutable::parse('2026-09-10'), 30);

    expect($slots)->toBe([]);
});
