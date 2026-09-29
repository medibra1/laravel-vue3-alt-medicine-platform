<?php

use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Services\SyncPractitionerAvailabilitiesAction;
use Illuminate\Database\QueryException;

test('replaces the whole schedule', function () {
    $practitioner = Practitioner::factory()->create();
    $practitioner->availabilities()->create(['day_of_week' => 5, 'start_time' => '10:00', 'end_time' => '11:00']);

    app(SyncPractitionerAvailabilitiesAction::class)->handle($practitioner, [
        ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '12:00'],
        ['day_of_week' => 1, 'start_time' => '14:00', 'end_time' => '18:00'],
    ]);

    $days = $practitioner->availabilities()->pluck('day_of_week')->all();
    expect($days)->toBe([1, 1]);
});

test('an empty slot list clears the schedule', function () {
    $practitioner = Practitioner::factory()->create();
    $practitioner->availabilities()->create(['day_of_week' => 5, 'start_time' => '10:00', 'end_time' => '11:00']);

    app(SyncPractitionerAvailabilitiesAction::class)->handle($practitioner, []);

    expect($practitioner->availabilities()->count())->toBe(0);
});

test('a failure mid-sync rolls back to the previous schedule', function () {
    $practitioner = Practitioner::factory()->create();
    $practitioner->availabilities()->create(['day_of_week' => 5, 'start_time' => '10:00', 'end_time' => '11:00']);

    // Missing end_time violates the NOT NULL column after the delete ran.
    expect(fn () => app(SyncPractitionerAvailabilitiesAction::class)->handle($practitioner, [
        ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => null],
    ]))->toThrow(QueryException::class);

    expect($practitioner->availabilities()->pluck('day_of_week')->all())->toBe([5]);
});
